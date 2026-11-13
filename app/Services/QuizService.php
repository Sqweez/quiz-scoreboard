<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Round;
use App\Models\Score;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuizService
{
    public function snapshot(Game $game): array
    {
        $game->loadMissing(['rounds', 'teams.scores']);
        $finished = $game->status === 'finished';
        return [
            'id' => $game->id,
            'title' => $game->title,
            'status' => $game->status,
            'statusLabel' => $finished ? 'Завершенная' : 'Открытая',
            'statusActionLabel' => $finished ? 'Просмотр' : 'Открыть',
            'rounds' => $game->rounds->map(fn ($round) => [
                'id' => $round->id, 'title' => $round->title,
                'maxScore' => $round->max_score, 'questionsCount' => $round->questions_count,
            ])->all(),
            'teams' => $game->teams->map(function ($team) use ($game) {
                $values = $team->scores->keyBy('round_id');
                return [
                    'id' => $team->id, 'name' => $team->name,
                    'scores' => $game->rounds->mapWithKeys(fn ($round) => [
                        $round->id => $this->normalize($values->get($round->id)?->value, $round->max_score),
                    ])->all(),
                ];
            })->all(),
            'createdAt' => $game->created_at->toISOString(),
            'updatedAt' => $game->updated_at->toISOString(),
        ];
    }

    public function create(string $userId, array $input): array
    {
        return DB::transaction(function () use ($userId, $input) {
            $game = Game::create(['user_id' => $userId, 'title' => $input['title'], 'status' => 'draft']);
            foreach ($input['rounds'] as $position => $round) {
                $game->rounds()->create([
                    'title' => $round['title'], 'position' => $position,
                    'max_score' => isset($round['maxScore']) ? max(0, $round['maxScore']) : null,
                    'questions_count' => $round['questionsCount'] ?? null,
                ]);
            }
            foreach ($input['teamNames'] as $position => $name) {
                $team = $game->teams()->create(['name' => $name, 'position' => $position]);
                $this->initializeScores($team, $game->rounds);
            }
            return $this->snapshot($game);
        });
    }

    public function change(Game $game, callable $operation): array
    {
        return DB::transaction(function () use ($game, $operation) {
            // Re-read under a row lock so concurrent edits see the latest status.
            $game = $game->newQuery()->lockForUpdate()->findOrFail($game->getKey());
            abort_if($game->status === 'finished', 409, 'Finished games are read-only.');
            $operation($game);
            $game->updated_at = now();
            $game->save();
            return $this->snapshot($game);
        });
    }

    public function addTeam(Game $game, string $name): void
    {
        $position = ($game->teams()->max('position') ?? -1) + 1;
        $team = $game->teams()->create(['name' => $name, 'position' => $position]);
        $this->initializeScores($team, $game->rounds);
    }

    public function deleteTeam(Game $game, Team $team): void
    {
        abort_if($game->teams()->count() <= 1, 422, 'At least one team must remain.');
        $team->delete();
    }

    public function addRound(Game $game, string $title): void
    {
        $position = ($game->rounds()->max('position') ?? -1) + 1;
        $round = $game->rounds()->create(['title' => $title, 'position' => $position]);
        foreach ($game->teams as $team) {
            $this->initializeScores($team, [$round]);
        }
    }

    public function updateRound(Round $round, array $input): void
    {
        $fields = [];
        foreach (['title' => 'title', 'maxScore' => 'max_score', 'questionsCount' => 'questions_count'] as $key => $column) {
            if (array_key_exists($key, $input)) {
                $fields[$column] = $key === 'maxScore' && $input[$key] !== null ? max(0, $input[$key]) : $input[$key];
            }
        }
        $round->update($fields);
        foreach ($round->scores as $score) {
            $score->update(['value' => $this->normalize($score->value, $round->max_score)]);
        }
    }

    public function deleteRound(Game $game, Round $round): void
    {
        abort_if($game->rounds()->count() <= 1, 422, 'At least one round must remain.');
        $round->delete();
    }

    public function scores(Game $game, array $updates): void
    {
        $teams = $game->teams->keyBy('id');
        $rounds = $game->rounds->keyBy('id');
        $rows = [];
        foreach ($updates as $update) {
            abort_unless($teams->has($update['teamId']) && $rounds->has($update['roundId']), 404, 'Team or round not found.');
            $key = $update['teamId'].':'.$update['roundId'];
            $rows[$key] = [
                'id' => (string) Str::uuid(), 'team_id' => $update['teamId'], 'round_id' => $update['roundId'],
                'value' => $this->normalize($update['score'], $rounds[$update['roundId']]->max_score),
            ];
        }
        if ($rows !== []) {
            Score::upsert(array_values($rows), ['team_id', 'round_id'], ['value']);
        }
    }

    private function initializeScores(Team $team, iterable $rounds): void
    {
        $rows = [];
        foreach ($rounds as $round) {
            $rows[] = ['id' => (string) Str::uuid(), 'team_id' => $team->id, 'round_id' => $round->id, 'value' => 0];
        }
        if ($rows !== []) {
            Score::insert($rows);
        }
    }

    private function normalize(mixed $value, ?float $max): float
    {
        $score = max(0, (float) $value);
        return $max === null ? $score : min($score, $max);
    }
}
