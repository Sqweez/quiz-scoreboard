<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\User;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QuizPleaseRatingGameTest extends TestCase
{
    use RefreshDatabase;

    private const EXTERNAL_ID = '01a0a6d4-60fa-715c-b8cf-f2bc6b44ddbf';
    private const PROFILE_URL = 'https://api.quizplease.com/api/user';
    private const SYNC_URL = 'https://rating-api.quizplease.ru/api/synchronize/auth/create';
    private const LOGIN_BY_TOKEN_URL = 'https://rating-api.quizplease.ru/api/auth/loginByToken';
    private const GAME_URL = 'https://rating-api.quizplease.ru/api/game';

    public function test_signs_in_through_the_admin_account_and_creates_the_game(): void
    {
        $user = $this->user(adminToken: 'admin-token');
        $game = $this->readyGame($user);
        Http::fake([
            self::PROFILE_URL => Http::response(['data' => [
                'id' => 5, 'name' => 'Host', 'email' => 'host@example.com', 'role' => ['name' => 'Admin', 'slug' => 'admin'],
                'managed_cities' => [['id' => 91, 'title' => 'Павлодар', 'slug' => 'pvl']],
            ]]),
            self::SYNC_URL => Http::response(['user_token' => ['token' => 'one-time']]),
            self::LOGIN_BY_TOKEN_URL => Http::response(['result' => ['user' => ['token' => '15|rating-secret', 'city' => ['id' => 91, 'code' => 'pvl']]]]),
            self::GAME_URL.'*' => $this->ratingGames([$this->ratingGame('019ea649-bcb9-7365-9ce2-f1c5339cc9af', 'кпклассика')]),
        ]);

        $this->actingAs($user)->postJson("/api/games/{$game->id}/rating")->assertOk()->assertJsonPath('status', 'finished');

        Http::assertSent(fn (Request $request) => $request->url() === self::PROFILE_URL && $request->hasHeader('Authorization', 'Bearer admin-token'));
        Http::assertSent(fn (Request $request) => $request->url() === self::SYNC_URL
            && $request->hasHeader('SynchronizeKey', 'test-sync-key')
            && $request->data() === ['name' => 'Host', 'email' => 'host@example.com', 'is_root' => false, 'cities' => [['code' => 'pvl', 'title' => 'Павлодар']]]);
        Http::assertSent(fn (Request $request) => $request->url() === self::LOGIN_BY_TOKEN_URL && $request['token'] === 'one-time');
        Http::assertSent(fn (Request $request) => $request->method() === 'GET' && $request->url() === self::GAME_URL.'?city=91'
            && $request->hasHeader('Authorization', 'Bearer rating-secret'));
        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->url() === self::GAME_URL
            && $request->hasHeader('Authorization', 'Bearer rating-secret')
            && $request->data() === [
                'city' => 'pvl',
                'schedule' => ['time' => '19:00', 'date' => '2026-09-30'],
                'rating' => 1,
                'title' => 'Квиз, плиз! PVL',
                'number' => self::EXTERNAL_ID,
                'package' => 'классика',
                'package_number' => '124',
                'is_count' => false,
            ]);
        $this->assertSame('15|rating-secret', $user->fresh()->quizplease_rating_token);
        $this->assertNotSame('15|rating-secret', DB::table('users')->where('id', $user->id)->value('quizplease_rating_token'));
        $this->assertNotNull($game->fresh()->rating_created_at);

        // A created game is not sent again.
        $this->postJson("/api/games/{$game->id}/rating")->assertStatus(409)->assertJsonPath('reason', 'rating_already_created');
        Http::assertSentCount(5);
    }

    public function test_game_already_listed_in_the_rating_is_marked_without_creating_a_duplicate(): void
    {
        $user = $this->user(ratingToken: '15|token');
        $game = $this->readyGame($user);
        Http::fake([self::GAME_URL.'*' => $this->ratingGames([$this->ratingGame(self::EXTERNAL_ID, 'классика')])]);

        $this->actingAs($user)->postJson("/api/games/{$game->id}/rating")->assertOk();

        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST');
        $this->assertNotNull($game->fresh()->rating_created_at);
    }

    public function test_package_suggestions_come_from_the_city_games_in_the_rating(): void
    {
        $user = $this->user(ratingToken: '15|token');
        Http::fake([self::GAME_URL.'*' => $this->ratingGames([
            $this->ratingGame('019ea649-bcb9-7365-9ce2-f1c5339cc9af', 'кпклассика'),
            $this->ratingGame('01a0cda2-af05-7255-898e-b6abf0168403', ' классика '),
            $this->ratingGame('01a0cda2-af05-7255-898e-b6abf0168404', 'классика'),
        ])]);

        $this->actingAs($user)->getJson('/api/quizplease/rating-packages')->assertOk()->assertExactJson(['классика', 'кпклассика']);
    }

    public function test_package_is_saved_on_create_and_edit(): void
    {
        $this->actingAs($this->user());

        $game = $this->postJson('/api/games', ['title' => 'Q', 'ratingPackage' => 'классика', 'teamNames' => ['A'], 'rounds' => [['title' => 'R']]])
            ->assertCreated()->assertJsonPath('ratingPackage', 'классика')->json();
        $this->patchJson("/api/games/{$game['id']}", ['ratingPackage' => 'кпклассика'])->assertOk()->assertJsonPath('ratingPackage', 'кпклассика');
        $this->patchJson("/api/games/{$game['id']}", ['ratingPackage' => null])->assertOk()->assertJsonPath('ratingPackage', null);
    }

    public function test_without_an_admin_token_the_admin_login_is_requested(): void
    {
        $user = $this->user();
        $game = $this->readyGame($user);
        Http::fake();

        $this->actingAs($user)->postJson("/api/games/{$game->id}/rating")->assertStatus(409)->assertJsonPath('reason', 'auth_required');
        Http::assertNothingSent();
    }

    public function test_expired_rating_token_is_renewed_once_and_rating_errors_are_passed_on(): void
    {
        $user = $this->user(ratingToken: '15|old', adminToken: 'admin-token');
        $game = $this->readyGame($user);
        Http::fake([
            self::PROFILE_URL => Http::response(['data' => ['name' => 'Host', 'email' => 'host@example.com', 'role' => ['slug' => 'admin'], 'managed_cities' => []]]),
            self::SYNC_URL => Http::response(['user_token' => ['token' => 'one-time']]),
            self::LOGIN_BY_TOKEN_URL => Http::response(['result' => ['user' => ['token' => '16|fresh', 'city' => ['id' => 91, 'code' => 'pvl']]]]),
            self::GAME_URL.'*' => function (Request $request) {
                if (!$request->hasHeader('Authorization', 'Bearer fresh')) {
                    return Http::response(['message' => 'Unauthenticated.'], 401);
                }

                return $request->method() === 'GET'
                    ? Http::response(['result' => [], 'totalCount' => 0])
                    : Http::response(['message' => 'Такая игра уже существует'], 422);
            },
        ]);

        $this->actingAs($user)->postJson("/api/games/{$game->id}/rating")->assertUnprocessable()
            ->assertJsonPath('reason', 'rating_rejected')->assertJsonPath('message', 'Такая игра уже существует');
        $this->assertSame('16|fresh', $user->fresh()->quizplease_rating_token);
        $this->assertNull($game->fresh()->rating_created_at);
    }

    public function test_incomplete_or_unfinished_games_are_not_sent(): void
    {
        $user = $this->user(ratingToken: '15|token');
        Http::fake();
        $this->actingAs($user);

        $draft = $this->readyGame($user);
        $draft->forceFill(['status' => GameStatus::Draft, 'finished_at' => null])->save();
        $this->postJson("/api/games/{$draft->id}/rating")->assertUnprocessable()->assertJsonPath('reason', 'rating_incomplete');

        $noCategory = $this->readyGame($user, ['external_id' => '01a0a6d4-60fa-715c-b8cf-f2bc6b44dd00', 'rating_category_id' => null]);
        $this->postJson("/api/games/{$noCategory->id}/rating")->assertUnprocessable()->assertJsonPath('reason', 'rating_incomplete');

        $noPackage = $this->readyGame($user, ['external_id' => '01a0a6d4-60fa-715c-b8cf-f2bc6b44dd01', 'rating_package' => null]);
        $this->postJson("/api/games/{$noPackage->id}/rating")->assertUnprocessable()->assertJsonPath('reason', 'rating_incomplete');

        Http::assertNothingSent();
    }

    public function test_other_users_cannot_create_the_rating_game(): void
    {
        $game = $this->readyGame($this->user());
        Http::fake();

        $this->actingAs($this->user('15|token'))->postJson("/api/games/{$game->id}/rating")->assertNotFound();
        Http::assertNothingSent();
    }

    // GET lists the city's games, POST creates one.
    private function ratingGames(array $games): \Closure
    {
        return fn (Request $request): PromiseInterface => $request->method() === 'GET'
            ? Http::response(['result' => $games, 'totalCount' => count($games)])
            : Http::response(['message' => 'Игра создана']);
    }

    private function ratingGame(string $number, string $package): array
    {
        return ['id' => random_int(1, 99999), 'number' => $number, 'title' => 'Квиз, плиз! PVL', 'package_entity' => ['id' => 1, 'title' => $package]];
    }

    private function user(?string $ratingToken = null, ?string $adminToken = null): User
    {
        $user = User::create(['email' => fake()->unique()->safeEmail(), 'password' => 'test-password']);
        $user->forceFill([
            'quizplease_token' => $adminToken,
            'quizplease_rating_token' => $ratingToken,
            'quizplease_rating_city' => $ratingToken ? 'pvl' : null,
            'quizplease_rating_city_id' => $ratingToken ? 91 : null,
        ])->save();

        return $user;
    }

    private function readyGame(User $user, array $overrides = []): Game
    {
        $id = $this->actingAs($user)->postJson('/api/games', ['title' => 'Квиз, плиз! PVL #124', 'teamNames' => ['A'], 'rounds' => [['title' => 'R']]])
            ->assertCreated()->json('id');
        $game = Game::findOrFail($id);
        $game->forceFill([
            'status' => GameStatus::Finished,
            'finished_at' => now(),
            'external_id' => self::EXTERNAL_ID,
            'extra' => ['title' => 'Квиз, плиз! PVL', 'gameNumber' => '124', 'packageNumber' => '#124квизплиз', 'date' => '2026-09-30 19:00'],
            'rating_category_id' => 1,
            'rating_include_scores' => false,
            'rating_package' => 'классика',
            ...$overrides,
        ])->save();

        return $game;
    }
}
