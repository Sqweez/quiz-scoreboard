<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGameRequest;
use App\Http\Requests\StoreRoundRequest;
use App\Http\Requests\TeamRequest;
use App\Http\Requests\UpdateGameRequest;
use App\Http\Requests\UpdateRoundRequest;
use App\Http\Requests\UpdateScoreRequest;
use App\Http\Requests\UpdateScoresRequest;
use App\Http\Resources\GameSummaryResource;
use App\Models\Game;
use App\Models\Round;
use App\Models\Team;
use App\Services\QuizService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GameController extends Controller
{
    public function __construct(private QuizService $quiz) {}

    public function active(Request $request): AnonymousResourceCollection
    {
        return GameSummaryResource::collection($this->summaries($request)->where('status', '!=', 'finished')
            ->orderByDesc('updated_at')->orderBy('id')->get());
    }

    public function finished(Request $request): AnonymousResourceCollection
    {
        return GameSummaryResource::collection($this->summaries($request)->where('status', 'finished')
            ->orderByDesc('finished_at')->orderBy('id')->paginate(15));
    }

    public function current(Request $request)
    {
        $game = Game::where('user_id', $request->user()->id)->orderByDesc('updated_at')->first();
        return response()->json($game ? $this->quiz->snapshot($game) : null);
    }

    public function show(Game $game): array
    {
        return $this->quiz->snapshot($game);
    }

    public function store(StoreGameRequest $request): array
    {
        return $this->quiz->create($request->user()->id, $request->validated());
    }

    public function update(UpdateGameRequest $request, Game $game): array
    {
        return $this->quiz->change($game, fn (Game $game) => $game->title = $request->validated('title'));
    }

    public function destroy(Game $game): array
    {
        $game->delete();
        return ['ok' => true];
    }

    public function finish(Game $game): array
    {
        return $this->quiz->change($game, fn (Game $game) => $game->finish());
    }

    public function addTeam(TeamRequest $request, Game $game): array
    {
        return $this->quiz->change($game, fn (Game $game) => $this->quiz->addTeam($game, $request->validated('name')));
    }

    public function renameTeam(TeamRequest $request, Game $game, Team $team): array
    {
        return $this->quiz->change($game, fn () => $team->update($request->validated()));
    }

    public function deleteTeam(Game $game, Team $team): array
    {
        return $this->quiz->change($game, fn (Game $game) => $this->quiz->deleteTeam($game, $team));
    }

    public function addRound(StoreRoundRequest $request, Game $game): array
    {
        return $this->quiz->change($game, fn (Game $game) => $this->quiz->addRound($game, $request->validated('title')));
    }

    public function updateRound(UpdateRoundRequest $request, Game $game, Round $round): array
    {
        return $this->quiz->change($game, fn () => $this->quiz->updateRound($round, $request->validated()));
    }

    public function deleteRound(Game $game, Round $round): array
    {
        return $this->quiz->change($game, fn (Game $game) => $this->quiz->deleteRound($game, $round));
    }

    public function scores(UpdateScoresRequest $request, Game $game): array
    {
        return $this->quiz->change($game, fn (Game $game) => $this->quiz->scores($game, $request->validated('updates')));
    }

    public function score(UpdateScoreRequest $request, Game $game): array
    {
        return $this->quiz->change($game, fn (Game $game) => $this->quiz->scores($game, [$request->validated()]));
    }

    private function summaries(Request $request): Builder
    {
        return Game::where('user_id', $request->user()->id)
            ->select(['id', 'title', 'updated_at', 'finished_at'])
            ->withCount(['teams', 'rounds']);
    }
}
