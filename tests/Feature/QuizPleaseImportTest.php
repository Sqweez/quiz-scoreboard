<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QuizPleaseImportTest extends TestCase
{
    use RefreshDatabase;

    private const GAME_ID = '01a19b42-0cc8-709f-8197-62881b0dbcdc';
    private const GAME_URL = 'https://api.quizplease.com/api/games/'.self::GAME_ID.'*';
    private const TOKEN_URL = 'https://api.quizplease.com/oauth/token';

    public function test_fetches_the_game_with_a_stored_token_without_creating_anything(): void
    {
        $user = $this->user('stored-token');
        Http::fake([self::GAME_URL => Http::response($this->game())]);

        $this->actingAs($user)->getJson('/api/quizplease/game?id='.self::GAME_ID)->assertOk()->assertExactJson([
            'externalId' => self::GAME_ID,
            'title' => 'Квиз, плиз! #42',
            'date' => '2026-11-20 19:30:00',
            'teams' => [
                ['externalId' => '10', 'name' => 'Совы'],
                ['externalId' => '30', 'name' => 'Ёжики'],
            ],
            'existingGameId' => null,
        ]);

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer stored-token')
            && str_contains(urldecode($request->url()), 'relationships[]=game_records.game_team'));
        $this->assertSame(0, Game::count());
        $this->assertSame(0, DB::table('teams')->count());
    }

    public function test_missing_token_requires_login_and_the_fetch_continues_after_it(): void
    {
        $user = $this->user();
        Http::fake([
            self::TOKEN_URL => Http::response(['token_type' => 'Bearer', 'expires_in' => 3600, 'access_token' => 'fresh-token', 'refresh_token' => 'r']),
            self::GAME_URL => Http::response($this->game()),
        ]);
        $this->actingAs($user);

        $this->getJson('/api/quizplease/game?id='.self::GAME_ID)->assertStatus(409)->assertJsonPath('reason', 'auth_required');
        Http::assertNothingSent();

        $this->postJson('/api/quizplease/login', ['username' => 'host@example.com', 'password' => 'secret-pass'])->assertNoContent();
        Http::assertSent(fn (Request $request) => $request->url() === self::TOKEN_URL
            && $request['username'] === 'host@example.com' && $request['password'] === 'secret-pass'
            && $request['grant_type'] === 'password' && $request['client_id'] === 'test-client' && $request['client_secret'] === 'test-secret');

        $this->getJson('/api/quizplease/game?id='.self::GAME_ID)->assertOk()->assertJsonCount(2, 'teams');

        $stored = DB::table('users')->where('id', $user->id)->value('quizplease_token');
        $this->assertNotSame('fresh-token', $stored);
        $this->assertSame('fresh-token', $user->fresh()->quizplease_token);
        $this->assertStringNotContainsString('secret-pass', json_encode(DB::table('users')->where('id', $user->id)->first()));
    }

    public function test_rejected_token_is_dropped_and_login_is_requested_again(): void
    {
        $user = $this->user('expired-token');
        Http::fake([self::GAME_URL => Http::response(['status' => 'error', 'message' => 'x', 'data' => null, 'errors' => []], 401)]);

        $this->actingAs($user)->getJson('/api/quizplease/game?id='.self::GAME_ID)
            ->assertStatus(409)->assertJsonPath('reason', 'auth_required');
        $this->assertNull($user->fresh()->quizplease_token);
    }

    public function test_wrong_credentials_are_reported_and_no_token_is_stored(): void
    {
        $user = $this->user();
        Http::fake([self::TOKEN_URL => Http::response(['status' => 'error', 'message' => 'Не правильное имя пользователя или пароль.', 'data' => null, 'errors' => []], 400)]);

        $this->actingAs($user)->postJson('/api/quizplease/login', ['username' => 'host@example.com', 'password' => 'wrong'])
            ->assertUnprocessable()->assertJsonPath('reason', 'invalid_credentials');
        $this->assertNull($user->fresh()->quizplease_token);
    }

    public function test_external_errors_are_not_returned_as_an_empty_import(): void
    {
        $this->actingAs($this->user('token'));

        $this->getJson('/api/quizplease/game?id=not-a-uuid')->assertUnprocessable()->assertJsonValidationErrors('id');
        $this->getJson('/api/quizplease/game?id=https://evil.example/x')->assertUnprocessable();

        Http::fake([self::GAME_URL => Http::sequence()
            ->push(['status' => 'error', 'data' => null], 404)
            ->push(['status' => 'error', 'data' => null], 403)
            ->push(['message' => 'Server Error'], 500)
            ->push(['data' => ['records' => []]])
            ->whenEmpty(fn () => throw new ConnectionException('timeout'))]);

        $url = '/api/quizplease/game?id='.self::GAME_ID;
        $this->getJson($url)->assertNotFound()->assertJsonPath('reason', 'not_found');
        $this->getJson($url)->assertForbidden()->assertJsonPath('reason', 'forbidden');
        $this->getJson($url)->assertStatus(502)->assertJsonPath('reason', 'unavailable');
        $this->getJson($url)->assertStatus(502)->assertJsonPath('reason', 'unavailable');
        $this->getJson($url)->assertStatus(502)->assertJsonPath('reason', 'unavailable');
    }

    public function test_only_confirmed_top_level_records_become_teams_once_per_external_team(): void
    {
        Http::fake([self::GAME_URL => Http::response($this->game())]);
        $this->actingAs($this->user('token'));

        $first = $this->getJson('/api/quizplease/game?id='.self::GAME_ID)->assertOk()->json('teams');
        $second = $this->getJson('/api/quizplease/game?id='.self::GAME_ID)->assertOk()->json('teams');

        $this->assertSame(['Совы', 'Ёжики'], array_column($first, 'name'));
        $this->assertSame($first, $second);
    }

    public function test_game_without_confirmed_records_has_no_teams(): void
    {
        Http::fake([self::GAME_URL => Http::response(['data' => [
            'id' => self::GAME_ID, 'title' => 'Пусто', 'game_number' => null, 'date' => null,
            'records' => [$this->record(1, 3, 10, 'Резерв')],
        ]])]);

        $this->actingAs($this->user('token'))->getJson('/api/quizplease/game?id='.self::GAME_ID)
            ->assertOk()->assertJsonPath('teams', [])->assertJsonPath('title', 'Пусто');
    }

    public function test_created_game_keeps_the_external_id_and_blocks_a_duplicate(): void
    {
        $user = $this->user('token');
        Http::fake([self::GAME_URL => Http::response($this->game())]);
        $this->actingAs($user);
        $payload = ['title' => 'Квиз, плиз! #42', 'externalId' => self::GAME_ID, 'teamNames' => ['Совы', 'Ёжики'], 'rounds' => [['title' => 'Раунд 1']]];

        $game = $this->postJson('/api/games', $payload)->assertCreated()->json();
        $this->assertSame(self::GAME_ID, Game::find($game['id'])->external_id);
        $this->assertSame(['Совы', 'Ёжики'], array_column($game['teams'], 'name'));

        $this->getJson('/api/quizplease/game?id='.self::GAME_ID)->assertOk()->assertJsonPath('existingGameId', $game['id']);
        $this->postJson('/api/games', $payload)->assertUnprocessable()->assertJsonValidationErrors('externalId');

        // Another user's game with the same external id is outside this user's scope.
        $this->actingAs($this->user('other'))->postJson('/api/games', $payload)->assertCreated();
    }

    public function test_manual_game_creation_still_works_without_an_external_id(): void
    {
        $this->actingAs($this->user());

        $game = $this->postJson('/api/games', ['title' => 'Manual', 'teamNames' => ['A'], 'rounds' => [['title' => 'R']]])->assertCreated()->json();

        $this->assertNull(Game::find($game['id'])->external_id);

        $manual = $this->postJson('/api/games', [
            'title' => 'Manual with id', 'externalId' => strtoupper(self::GAME_ID), 'teamNames' => ['A'], 'rounds' => [['title' => 'R']],
        ])->assertCreated()->json();
        $this->assertSame(self::GAME_ID, Game::find($manual['id'])->external_id);
        $this->postJson('/api/games', ['title' => 'Bad', 'externalId' => 'nope', 'teamNames' => ['A'], 'rounds' => [['title' => 'R']]])
            ->assertUnprocessable()->assertJsonValidationErrors('externalId');
    }

    public function test_external_id_can_be_set_changed_and_cleared_when_editing(): void
    {
        $this->actingAs($this->user());
        $game = $this->postJson('/api/games', ['title' => 'Manual', 'teamNames' => ['A'], 'rounds' => [['title' => 'R']]])->assertCreated()->json();
        $other = $this->postJson('/api/games', ['title' => 'Other', 'externalId' => self::GAME_ID, 'teamNames' => ['A'], 'rounds' => [['title' => 'R']]])->assertCreated()->json();
        $url = "/api/games/{$game['id']}";
        $newId = '0190a1b2-0cc8-709f-8197-62881b0dbcdd';

        $this->patchJson($url, ['externalId' => strtoupper($newId)])->assertOk()
            ->assertJsonPath('externalId', $newId)->assertJsonPath('title', 'Manual');
        $this->patchJson($url, ['externalId' => $newId])->assertOk();
        $this->patchJson($url, ['externalId' => 'nope'])->assertUnprocessable()->assertJsonValidationErrors('externalId');
        $this->patchJson($url, ['externalId' => self::GAME_ID])->assertUnprocessable()->assertJsonValidationErrors('externalId');
        $this->patchJson($url, ['title' => 'Renamed'])->assertOk()->assertJsonPath('externalId', $newId);
        $this->patchJson($url, ['externalId' => null])->assertOk()->assertJsonPath('externalId', null);

        // Finished games keep title and external id editable, everything else stays closed.
        $this->postJson("/api/games/{$other['id']}/finish")->assertOk();
        $this->patchJson("/api/games/{$other['id']}", ['externalId' => null, 'title' => 'Played'])->assertOk()
            ->assertJsonPath('externalId', null)->assertJsonPath('title', 'Played')->assertJsonPath('status', 'finished');
        $this->postJson("/api/games/{$other['id']}/teams", ['name' => 'Late'])->assertConflict();
    }

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/quizplease/game?id='.self::GAME_ID)->assertUnauthorized();
        $this->postJson('/api/quizplease/login', ['username' => 'a', 'password' => 'b'])->assertUnauthorized();
    }

    private function user(?string $token = null): User
    {
        $user = User::create(['email' => fake()->unique()->safeEmail(), 'password' => 'test-password']);
        $user->forceFill(['quizplease_token' => $token])->save();

        return $user;
    }

    private function game(): array
    {
        return ['status' => 'success', 'data' => [
            'id' => self::GAME_ID,
            'title' => ' Квиз, плиз! ',
            'game_number' => 42,
            'date' => '2026-11-20 19:30:00',
            'records' => [
                $this->record(1, 1, 10, 'Совы'),
                $this->record(2, 0, 20, 'Не подтвердили'),
                $this->record(3, 1, 30, 'Ёжики'),
                $this->record(4, 3, 40, 'Резерв'),
                $this->record(5, 4, 50, 'Удалена'),
                $this->record(6, 2, 60, 'Не пришли'),
                $this->record(7, 5, 70, 'Ждут оплату'),
                $this->record(8, 1, 10, 'Совы'),
                $this->record(9, 1, 90, 'Присоединённая', joinedTo: 1),
            ],
        ]];
    }

    private function record(int $id, int $status, int $teamId, string $title, ?int $joinedTo = null): array
    {
        return [
            'id' => $id, 'status' => $status, 'joined_to_record_id' => $joinedTo, 'people_count' => 5,
            'team' => ['id' => $teamId, 'title' => $title, 'captain' => 'Капитан', 'phone' => '+70000000000', 'email' => 'team@example.com'],
        ];
    }
}
