<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SupabaseTransferTest extends TestCase
{
    use RefreshDatabase;

    private array $rows;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.connections.supabase_source' => array_merge(config('database.connections.pgsql'), [
            'url' => 'postgresql://quiz:quiz@127.0.0.1:55439/quiz_source', 'host' => '127.0.0.1', 'port' => '55439', 'database' => 'quiz_source', 'username' => 'quiz', 'password' => 'quiz',
        ])]);
        DB::purge('supabase_source');
        $source = DB::connection('supabase_source');
        $source->unprepared('CREATE SCHEMA IF NOT EXISTS auth; DROP TABLE IF EXISTS scores, teams, rounds, games, auth.users CASCADE;
            CREATE TABLE auth.users (id uuid PRIMARY KEY, email text, encrypted_password text, email_confirmed_at timestamptz, created_at timestamptz, updated_at timestamptz);
            CREATE TABLE games (id uuid PRIMARY KEY, user_id uuid, title text, status text, created_at timestamp(3), updated_at timestamp(3));
            CREATE TABLE rounds (id uuid PRIMARY KEY, game_id uuid, title text, max_score double precision, questions_count integer, position integer);
            CREATE TABLE teams (id uuid PRIMARY KEY, game_id uuid, name text, position integer);
            CREATE TABLE scores (id uuid PRIMARY KEY, team_id uuid, round_id uuid, value double precision)');
        $user = '10000000-0000-4000-8000-000000000001';
        $game = '20000000-0000-4000-8000-000000000001';
        $round = '30000000-0000-4000-8000-000000000001';
        $team = '40000000-0000-4000-8000-000000000001';
        $this->rows = [
            'auth.users' => [['id' => $user, 'email' => 'import@example.test', 'encrypted_password' => password_hash('synthetic-password', PASSWORD_BCRYPT, ['cost' => 4]), 'email_confirmed_at' => '2024-01-02 10:12:13.123456+00', 'created_at' => '2024-01-01 10:12:13.654321+00', 'updated_at' => '2024-01-02 10:12:13.123456+00']],
            'games' => [['id' => $game, 'user_id' => $user, 'title' => 'Старая игра', 'status' => 'finished', 'created_at' => '2024-02-01 11:12:13.123', 'updated_at' => '2024-02-02 11:12:13.987']],
            'rounds' => [['id' => $round, 'game_id' => $game, 'title' => 'Раунд', 'max_score' => null, 'questions_count' => null, 'position' => 3]],
            'teams' => [['id' => $team, 'game_id' => $game, 'name' => 'Команда', 'position' => 2]],
            'scores' => [['id' => '50000000-0000-4000-8000-000000000001', 'team_id' => $team, 'round_id' => $round, 'value' => 12.75]],
        ];
        foreach ($this->rows as $table => $rows) {
            $source->table($table)->insert($rows);
        }
    }

    public function test_preserves_every_field_existing_password_and_source(): void
    {
        $before = $this->snapshot();
        $this->artisan('supabase:import')->assertSuccessful();
        $this->artisan('supabase:import --verify-only')->assertSuccessful();
        $user = DB::table('users')->first();
        $this->assertSame($this->rows['auth.users'][0]['encrypted_password'], $user->password);
        $this->assertSame('2024-01-01 10:12:13.654321+00', $user->created_at);
        $this->assertSame('2024-01-02 10:12:13.123456+00', $user->email_verified_at);
        foreach (['games', 'rounds', 'teams', 'scores'] as $table) {
            $this->assertEquals($before[$table], DB::table($table)->orderBy('id')->get()->all());
        }
        $this->assertTrue(Auth::attempt(['email' => 'import@example.test', 'password' => 'synthetic-password']));
        $this->assertSame($user->id, Auth::id());
        $this->assertEquals($before, $this->snapshot());
    }

    public function test_imported_supabase_2a_password_authenticates_and_rehashes(): void
    {
        $this->assertImportedBcryptLogin('2a');
    }

    public function test_imported_supabase_2b_password_authenticates_and_rehashes(): void
    {
        $this->assertImportedBcryptLogin('2b');
    }

    private function assertImportedBcryptLogin(string $prefix): void
    {
        $hash = substr_replace($this->rows['auth.users'][0]['encrypted_password'], $prefix, 1, 2);
        DB::connection('supabase_source')->table('auth.users')->update(['encrypted_password' => $hash]);
        $this->artisan('supabase:import')->assertSuccessful();
        $this->assertSame($hash, DB::table('users')->value('password'));
        $this->artisan('supabase:import --verify-only')->assertSuccessful();
        $this->assertTrue(config('hashing.bcrypt.verify'));
        $this->assertFalse(Auth::attempt(['email' => 'import@example.test', 'password' => 'wrong-password']));
        $this->assertSame($hash, DB::table('users')->value('password'));
        $this->assertTrue(Auth::attempt(['email' => 'import@example.test', 'password' => 'synthetic-password']));
        $rehash = DB::table('users')->value('password');
        $this->assertStringStartsWith('$2y$', $rehash);
        $this->assertTrue(password_verify('synthetic-password', $rehash));
        $this->assertSame($hash, DB::connection('supabase_source')->table('auth.users')->value('encrypted_password'));
    }

    public function test_unsupported_algorithms_and_malformed_supabase_bcrypt_remain_rejected(): void
    {
        $this->assertSame('unknown', Hash::info('$2a$03$'.str_repeat('a', 53))['algoName']);
        $this->assertSame('unknown', Hash::info('$2b$04$'.str_repeat('a', 52))['algoName']);
        $this->assertTrue(config('hashing.bcrypt.verify'));
        $this->expectException(\RuntimeException::class);
        Hash::check('synthetic-password', password_hash('synthetic-password', PASSWORD_ARGON2ID));
    }

    public function test_verification_detects_changed_cell_and_user_field_without_writes(): void
    {
        $this->artisan('supabase:import')->assertSuccessful();
        DB::table('scores')->update(['value' => 12.5]);
        $this->artisan('supabase:import --verify-only')->assertFailed();
        $this->assertSame(12.5, DB::table('scores')->value('value'));
        DB::table('scores')->update(['value' => 12.75]);
        DB::table('users')->update(['email_verified_at' => null]);
        $this->artisan('supabase:import --verify-only')->assertFailed();
    }

    public function test_refuses_nonempty_target(): void
    {
        $this->artisan('supabase:import')->assertSuccessful();
        $before = DB::table('users')->get()->all();
        $this->artisan('supabase:import')->assertFailed();
        $this->assertEquals($before, DB::table('users')->get()->all());
        $this->assertDatabaseCount('scores', 1);
    }

    public function test_malformed_source_rolls_back_without_modifying_source(): void
    {
        DB::connection('supabase_source')->table('games')->update(['status' => 'unknown']);
        $before = $this->snapshot();
        $this->artisan('supabase:import')->assertFailed();
        foreach (['users', 'games', 'rounds', 'teams', 'scores'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertEquals($before, $this->snapshot());
    }

    public function test_database_failure_after_copying_users_rolls_back_all_target_records(): void
    {
        DB::connection('supabase_source')->table('scores')->update(['value' => null]);
        $before = $this->snapshot();
        $this->artisan('supabase:import')->assertFailed();
        foreach (['users', 'games', 'rounds', 'teams', 'scores'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertEquals($before, $this->snapshot());
    }

    public function test_import_verifies_more_than_one_uuid_chunk(): void
    {
        $users = [];
        for ($index = 2; $index <= 502; $index++) {
            $user = $this->rows['auth.users'][0];
            $user['id'] = '10000000-0000-4000-8000-'.sprintf('%012d', $index);
            $user['email'] = 'import'.$index.'@example.test';
            $users[] = $user;
        }
        DB::connection('supabase_source')->table('auth.users')->insert($users);
        $this->artisan('supabase:import')->assertSuccessful();
        $this->artisan('supabase:import --verify-only')->assertSuccessful();
        $this->assertDatabaseCount('users', 502);
    }

    public function test_rejects_incompatible_hash_duplicate_email_and_orphan_owner(): void
    {
        $source = DB::connection('supabase_source');
        $source->table('auth.users')->update(['encrypted_password' => 'not-bcrypt']);
        $this->artisan('supabase:import')->assertFailed();
        $source->table('auth.users')->update(['encrypted_password' => $this->rows['auth.users'][0]['encrypted_password']]);
        $duplicate = $this->rows['auth.users'][0];
        $duplicate['id'] = '10000000-0000-4000-8000-000000000002';
        $source->table('auth.users')->insert($duplicate);
        $this->artisan('supabase:import')->assertFailed();
        $source->table('auth.users')->where('id', $duplicate['id'])->delete();
        $source->table('games')->update(['user_id' => $duplicate['id']]);
        $this->artisan('supabase:import')->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_rejects_cross_game_scores(): void
    {
        $source = DB::connection('supabase_source');
        $other = $this->rows['games'][0];
        $other['id'] = '20000000-0000-4000-8000-000000000002';
        $source->table('games')->insert($other);
        $source->table('rounds')->update(['game_id' => $other['id']]);
        $this->artisan('supabase:import')->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_refuses_same_source_and_target(): void
    {
        config(['database.connections.supabase_source' => config('database.connections.'.config('database.default'))]);
        config(['database.connections.supabase_source.url' => 'postgresql://quiz:quiz@127.0.0.1:55439/quiz_test']);
        DB::purge('supabase_source');
        $this->artisan('supabase:import')->expectsOutputToContain('Source and target must be different databases.')->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_requires_explicit_source_url(): void
    {
        config(['database.connections.supabase_source.url' => null]);
        DB::purge('supabase_source');
        $this->artisan('supabase:import')->expectsOutputToContain('SUPABASE_SOURCE_URL must be configured explicitly.')->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach (array_keys($this->rows) as $table) {
            $snapshot[$table] = DB::connection('supabase_source')->table($table)->orderBy('id')->get()->all();
        }
        return $snapshot;
    }
}
