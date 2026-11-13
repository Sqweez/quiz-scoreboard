<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Http\Requests\FinishedGamesRequest;
use App\Http\Requests\StoreGameRequest;
use App\Http\Requests\StoreRoundRequest;
use App\Http\Requests\TeamRequest;
use App\Http\Requests\UpdateGameRequest;
use App\Http\Requests\UpdateRoundRequest;
use App\Http\Requests\UpdateScoreRequest;
use App\Http\Requests\UpdateScoresRequest;
use App\Http\Resources\GameResource;
use App\Http\Resources\GameSummaryResource;
use App\Models\Game;
use App\Models\Round;
use App\Models\Team;
use App\Exceptions\QuizPleaseException;
use App\Services\QuizPleaseClient;
use App\Services\QuizPleaseRatingClient;
use App\Services\QuizService;
use App\Services\RatingResultsSheet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GameController extends Controller
{
    public function __construct(private QuizService $quiz, private QuizPleaseClient $quizPlease) {}

    public function active(Request $request): AnonymousResourceCollection
    {
        return GameSummaryResource::collection($this->summaries($request)->where('status', '!=', GameStatus::Finished)
            ->orderByDesc('updated_at')->orderBy('id')->get());
    }

    public function finished(FinishedGamesRequest $request): AnonymousResourceCollection
    {
        $search = $request->validated('search');

        return GameSummaryResource::collection($this->summaries($request)->where('status', GameStatus::Finished)
            // Escape LIKE wildcards so the search matches the typed text literally.
            ->when($search, fn (Builder $query) => $query->where('title', 'ilike', '%'.addcslashes($search, '\\%_').'%'))
            ->orderByDesc('finished_at')->orderBy('id')->paginate(10)->withQueryString());
    }

    public function current(Request $request): GameResource|JsonResponse
    {
        $game = Game::where('user_id', $request->user()->id)->orderByDesc('updated_at')->first();
        return $game ? new GameResource($game) : response()->json(null);
    }

    public function show(Game $game): GameResource
    {
        return new GameResource($game);
    }

    public function store(StoreGameRequest $request): GameResource
    {
        $input = $request->validated();
        // Linked games always get a fresh snapshot of the Quiz Please game, also when the id was typed by hand.
        $extra = isset($input['externalId']) ? $this->quizPlease->game($request->user(), strtolower($input['externalId']))['extra'] : null;

        return new GameResource($this->quiz->create($request->user()->id, $input, $extra));
    }

    public function update(UpdateGameRequest $request, Game $game): GameResource
    {
        $input = $request->validated();
        $linksGame = array_key_exists('externalId', $input);
        $externalId = $linksGame && $input['externalId'] !== null ? strtolower($input['externalId']) : null;
        // Refetch the snapshot when the link changes or was saved before snapshots existed; fetched outside the DB lock.
        $extra = $externalId !== null && ($externalId !== $game->external_id || $game->extra === null)
            ? $this->quizPlease->game($request->user(), $externalId)['extra']
            : $game->extra;

        return new GameResource($this->quiz->change($game, function (Game $game) use ($input, $linksGame, $externalId, $extra) {
            if (array_key_exists('title', $input)) {
                $game->title = $input['title'];
            }
            if ($linksGame) {
                $game->external_id = $externalId;
                $game->extra = $externalId === null ? null : $extra;
            }
            if (array_key_exists('ratingCategoryId', $input)) {
                $game->rating_category_id = $input['ratingCategoryId'];
            }
            if (array_key_exists('ratingIncludeScores', $input)) {
                $game->rating_include_scores = $input['ratingIncludeScores'];
            }
            if (array_key_exists('ratingPackage', $input)) {
                $game->rating_package = $input['ratingPackage'];
            }
        }, allowFinished: true));
    }

    public function destroy(Game $game): array
    {
        $game->delete();
        return ['ok' => true];
    }

    public function finish(Game $game): GameResource
    {
        return new GameResource($this->quiz->change($game, fn (Game $game) => $game->finish()));
    }

    // Creates the game in the Quiz Please rating module; results are uploaded there separately.
    // Creates the game in the Quiz Please rating module and queues its results table there. The two steps are
    // recorded separately, so a failed upload is retried without creating the game again.
    public function uploadRating(Request $request, Game $game, QuizPleaseRatingClient $rating, RatingResultsSheet $results): GameResource
    {
        throw_unless($game->isReadyForRating(), QuizPleaseException::ratingIncomplete());
        throw_if($game->rating_uploaded_at !== null, QuizPleaseException::ratingAlreadyUploaded());

        if ($game->rating_created_at === null) {
            $game = $this->createInRating($request, $game, $rating);
        }

        $path = tempnam(sys_get_temp_dir(), 'rating-results');
        try {
            $results->write($game, $path);
            $rating->queueResults($request->user(), $game, $path);
        } finally {
            unlink($path);
        }

        return new GameResource($this->quiz->change($game, fn (Game $game) => $game->rating_uploaded_at = now(), allowFinished: true));
    }

    // Only creates the rating game, for uploading the results by hand when the queue fails.
    public function createRatingGame(Request $request, Game $game, QuizPleaseRatingClient $rating): GameResource
    {
        throw_unless($game->isReadyForRating(), QuizPleaseException::ratingIncomplete());
        throw_if($game->rating_created_at !== null, QuizPleaseException::ratingAlreadyCreated());

        return new GameResource($this->createInRating($request, $game, $rating));
    }

    // The results table to upload to the rating by hand, named by the Quiz Please game id as the rating expects.
    public function ratingResults(Game $game, RatingResultsSheet $results): BinaryFileResponse
    {
        throw_unless($game->isReadyForRating(), QuizPleaseException::ratingIncomplete());

        $path = tempnam(sys_get_temp_dir(), 'rating-results');
        $results->write($game, $path);

        return response()->download($path, "{$game->external_id}.xlsx")->deleteFileAfterSend();
    }

    private function createInRating(Request $request, Game $game, QuizPleaseRatingClient $rating): Game
    {
        $rating->createGame($request->user(), $game);

        return $this->quiz->change($game, fn (Game $game) => $game->rating_created_at = now(), allowFinished: true);
    }

    public function addTeam(TeamRequest $request, Game $game): GameResource
    {
        return new GameResource($this->quiz->change($game, fn (Game $game) => $this->quiz->addTeam($game, $request->validated('name'))));
    }

    public function renameTeam(TeamRequest $request, Game $game, Team $team): GameResource
    {
        return new GameResource($this->quiz->change($game, fn () => $team->update($request->validated())));
    }

    public function deleteTeam(Game $game, Team $team): GameResource
    {
        return new GameResource($this->quiz->change($game, fn (Game $game) => $this->quiz->deleteTeam($game, $team)));
    }

    public function addRound(StoreRoundRequest $request, Game $game): GameResource
    {
        return new GameResource($this->quiz->change($game, fn (Game $game) => $this->quiz->addRound($game, $request->validated('title'))));
    }

    public function updateRound(UpdateRoundRequest $request, Game $game, Round $round): GameResource
    {
        return new GameResource($this->quiz->change($game, fn () => $this->quiz->updateRound($round, $request->validated())));
    }

    public function deleteRound(Game $game, Round $round): GameResource
    {
        return new GameResource($this->quiz->change($game, fn (Game $game) => $this->quiz->deleteRound($game, $round)));
    }

    public function scores(UpdateScoresRequest $request, Game $game): GameResource
    {
        return new GameResource($this->quiz->change($game, fn (Game $game) => $this->quiz->scores($game, $request->validated('updates'))));
    }

    public function score(UpdateScoreRequest $request, Game $game): GameResource
    {
        return new GameResource($this->quiz->change($game, fn (Game $game) => $this->quiz->scores($game, [$request->validated()])));
    }

    private function summaries(Request $request): Builder
    {
        return Game::where('user_id', $request->user()->id)
            ->select(['id', 'title', 'updated_at', 'finished_at'])
            ->withCount(['teams', 'rounds']);
    }
}
