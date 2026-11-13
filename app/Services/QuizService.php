<?php

namespace App\Services;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\Round;
use App\Models\Score;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuizService
{
    public function create(string $userId, array $input, ?array $extra = null): Game
    {
        return DB::transaction(function () use ($userId, $input, $extra) {
            $game = Game::create([
                'user_id' => $userId, 'title' => $input['title'], 'status' => GameStatus::Draft,
                'external_id' => isset($input['externalId']) ? strtolower($input['externalId']) : null,
                'extra' => $extra,
                'rating_category_id' => $input['ratingCategoryId'] ?? null,
                'rating_include_scores' => $input['ratingIncludeScores'] ?? true,
            ]);
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
            return $game;
        });
    }

    // Finished games are read-only unless the operation only touches game details (title, external id).
    public function change(Game $game, callable $operation, bool $allowFinished = false): Game
    {
        return DB::transaction(function () use ($game, $operation, $allowFinished) {
            // Re-read under a row lock so concurrent edits see the latest status.
            $game = $game->newQuery()->lockForUpdate()->findOrFail($game->getKey());
            abort_if(!$allowFinished && $game->status === GameStatus::Finished, 409, 'Finished games are read-only.');
            $operation($game);
            $game->updated_at = now();
            $game->save();
            return $game;
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
            $score->update(['value' => $round->normalizeScore($score->value)]);
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
                'value' => $rounds[$update['roundId']]->normalizeScore($update['score']),
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
}
