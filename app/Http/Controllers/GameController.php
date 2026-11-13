<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Services\QuizService;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function __construct(private QuizService $quiz) {}

    public function index(Request $request): array
    {
        return Game::where('user_id', $request->user()->id)->with(['rounds', 'teams.scores'])->orderByDesc('updated_at')->get()
            ->map(fn ($game) => $this->quiz->snapshot($game))->all();
    }

    public function current(Request $request)
    {
        $game = Game::where('user_id', $request->user()->id)->orderByDesc('updated_at')->first();
        return response()->json($game ? $this->quiz->snapshot($game) : null);
    }

    public function show(Request $request, string $id): array
    {
        return $this->quiz->snapshot($this->quiz->owned($request->user()->id, $id));
    }

    public function store(Request $request): array
    {
        $input = $request->validate([
            'title' => ['required', 'string'],
            'teamNames' => ['required', 'array', 'min:1'], 'teamNames.*' => ['required', 'string'],
            'rounds' => ['required', 'array', 'min:1'], 'rounds.*.title' => ['required', 'string'],
            'rounds.*.maxScore' => ['nullable', 'numeric'],
            'rounds.*.questionsCount' => ['nullable', 'integer', 'min:0'],
        ]);
        return $this->quiz->create($request->user()->id, $input);
    }

    public function update(Request $request, string $id): array
    {
        $input = $request->validate(['title' => ['required', 'string']]);
        return $this->quiz->change($request->user()->id, $id, fn ($game) => $game->title = $input['title']);
    }

    public function destroy(Request $request, string $id): array
    {
        $this->quiz->owned($request->user()->id, $id)->delete();
        return ['ok' => true];
    }

    public function finish(Request $request, string $id): array
    {
        return $this->quiz->change($request->user()->id, $id, fn ($game) => $game->status = 'finished');
    }

    public function addTeam(Request $request, string $id): array
    {
        $input = $request->validate(['name' => ['required', 'string']]);
        return $this->quiz->change($request->user()->id, $id, fn ($game) => $this->quiz->addTeam($game, $input['name']));
    }

    public function renameTeam(Request $request, string $id, string $teamId): array
    {
        $input = $request->validate(['name' => ['required', 'string']]);
        return $this->quiz->change($request->user()->id, $id, fn ($game) => $game->teams()->findOrFail($teamId)->update($input));
    }

    public function deleteTeam(Request $request, string $id, string $teamId): array
    {
        return $this->quiz->change($request->user()->id, $id, fn ($game) => $this->quiz->deleteTeam($game, $teamId));
    }

    public function addRound(Request $request, string $id): array
    {
        $input = $request->validate(['title' => ['required', 'string']]);
        return $this->quiz->change($request->user()->id, $id, fn ($game) => $this->quiz->addRound($game, $input['title']));
    }

    public function updateRound(Request $request, string $id, string $roundId): array
    {
        $input = $request->validate([
            'title' => ['sometimes', 'required', 'string'], 'maxScore' => ['sometimes', 'nullable', 'numeric'],
            'questionsCount' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);
        return $this->quiz->change($request->user()->id, $id, fn ($game) => $this->quiz->updateRound($game, $roundId, $input));
    }

    public function deleteRound(Request $request, string $id, string $roundId): array
    {
        return $this->quiz->change($request->user()->id, $id, fn ($game) => $this->quiz->deleteRound($game, $roundId));
    }

    public function scores(Request $request, string $id): array
    {
        $input = $request->validate([
            'updates' => ['present', 'array'], 'updates.*.teamId' => ['required', 'uuid'],
            'updates.*.roundId' => ['required', 'uuid'], 'updates.*.score' => ['present', 'nullable', 'numeric'],
        ]);
        return $this->quiz->change($request->user()->id, $id, fn ($game) => $this->quiz->scores($game, $input['updates']));
    }

    public function score(Request $request, string $id): array
    {
        $input = $request->validate(['teamId' => ['required', 'uuid'], 'roundId' => ['required', 'uuid'], 'score' => ['present', 'nullable', 'numeric']]);
        return $this->quiz->change($request->user()->id, $id, fn ($game) => $this->quiz->scores($game, [$input]));
    }
}
