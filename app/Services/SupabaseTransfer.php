<?php

namespace App\Services;

use DomainException;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SupabaseTransfer
{
    private const FIELDS = [
        'users' => ['id', 'email', 'password', 'email_verified_at', 'created_at', 'updated_at'],
        'games' => ['id', 'user_id', 'title', 'status', 'created_at', 'updated_at'],
        'rounds' => ['id', 'game_id', 'title', 'max_score', 'questions_count', 'position'],
        'teams' => ['id', 'game_id', 'name', 'position'],
        'scores' => ['id', 'team_id', 'round_id', 'value'],
    ];

    public function run(bool $verifyOnly): array
    {
        if (! is_string(config('database.connections.supabase_source.url')) || config('database.connections.supabase_source.url') === '') {
            throw new DomainException('SUPABASE_SOURCE_URL must be configured explicitly.');
        }
        $source = DB::connection('supabase_source');
        $target = DB::connection();
        if ($source->getDriverName() !== 'pgsql' || $target->getDriverName() !== 'pgsql') {
            throw new DomainException('Import requires PostgreSQL source and target.');
        }

        return $source->transaction(function () use ($source, $target, $verifyOnly) {
            $source->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            $source->statement("SET LOCAL TIME ZONE 'UTC'");
            $identity = 'SELECT current_database() AS database, (SELECT oid FROM pg_database WHERE datname = current_database()) AS oid, extract(epoch FROM pg_postmaster_start_time()) AS started';
            if ($source->selectOne($identity) == $target->selectOne($identity)) {
                throw new DomainException('Source and target must be different databases.');
            }

            return $target->transaction(function () use ($source, $target, $verifyOnly) {
                $target->statement("SET LOCAL TIME ZONE 'UTC'");
                $target->statement('LOCK TABLE users, games, rounds, teams, scores IN '.($verifyOnly ? 'SHARE' : 'ACCESS EXCLUSIVE').' MODE');
                if (! $verifyOnly) {
                    foreach (array_keys(self::FIELDS) as $table) {
                        if ($target->table($table)->exists()) {
                            throw new DomainException('Target must be empty; no records were imported.');
                        }
                    }
                }
                $this->validateRelations($source);
                $counts = [];
                foreach (self::FIELDS as $table => $fields) {
                    $counts[$table] = $this->sourceQuery($source, $table)->count();
                    $this->sourceQuery($source, $table)->chunkById(500, function ($rows) use ($table, $fields, $target, $verifyOnly) {
                        $records = $rows->map(fn ($row) => (array) $row)->all();
                        if ($table === 'users') {
                            foreach ($records as $record) {
                                if (! is_string($record['email']) || $record['email'] === '' || ! is_string($record['password']) || Hash::info($record['password'])['algoName'] !== 'bcrypt') {
                                    throw new DomainException('Source contains an account without an email or compatible bcrypt password.');
                                }
                            }
                        }
                        if (! $verifyOnly) {
                            $target->table($table)->insert($records);
                        }
                        $actual = $target->table($table)->select($fields)->whereIn('id', array_column($records, 'id'))->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
                        if ($actual !== $records) {
                            throw new DomainException('Field verification failed for '.$table.'.');
                        }
                    });
                    if ($target->table($table)->count() !== $counts[$table]) {
                        throw new DomainException('Row count verification failed for '.$table.'.');
                    }
                }

                return $counts;
            });
        });
    }

    private function sourceQuery(Connection $source, string $table): Builder
    {
        if ($table === 'users') {
            return $source->table('auth.users')->selectRaw("id, email, encrypted_password AS password, email_confirmed_at AS email_verified_at, created_at, updated_at");
        }

        return $source->table($table)->select(self::FIELDS[$table]);
    }

    private function validateRelations(Connection $source): void
    {
        if ($source->table('auth.users')->select('email')->groupBy('email')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new DomainException('Source contains duplicate account emails.');
        }
        if ($source->table('games')->whereNotIn('status', ['draft', 'finished'])->orWhereNull('status')->exists()) {
            throw new DomainException('Source contains an invalid game status.');
        }
        foreach ([['games', 'user_id', 'auth.users'], ['rounds', 'game_id', 'games'], ['teams', 'game_id', 'games'], ['scores', 'team_id', 'teams'], ['scores', 'round_id', 'rounds']] as [$table, $foreignKey, $parent]) {
            if ($source->table($table)->leftJoin($parent, $table.'.'.$foreignKey, '=', $parent.'.id')->whereNull($parent.'.id')->exists()) {
                throw new DomainException('Source contains an orphan record in '.$table.'.');
            }
        }
        if ($source->table('scores')->join('teams', 'scores.team_id', '=', 'teams.id')->join('rounds', 'scores.round_id', '=', 'rounds.id')->whereColumn('teams.game_id', '<>', 'rounds.game_id')->exists()) {
            throw new DomainException('Source contains a score connecting different games.');
        }
    }
}
