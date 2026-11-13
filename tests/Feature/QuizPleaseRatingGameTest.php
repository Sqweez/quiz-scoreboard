<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QuizPleaseRatingGameTest extends TestCase
{
    use RefreshDatabase;

    private const EXTERNAL_ID = '01a0a6d4-60fa-715c-b8cf-f2bc6b44ddbf';
    private const LOGIN_URL = 'https://rating-api.quizplease.ru/api/auth/login';
    private const GAME_URL = 'https://rating-api.quizplease.ru/api/game';

    public function test_login_is_requested_first_and_the_game_is_created_after_it(): void
    {
        $user = $this->user();
        $game = $this->readyGame($user);
        Http::fake([
            self::LOGIN_URL => Http::response(['message' => 'ok', 'result' => ['user' => ['token' => '15|rating-secret', 'city' => ['id' => 7, 'code' => 'pvl']]]]),
            self::GAME_URL => Http::response(['message' => 'Игра создана']),
        ]);
        $this->actingAs($user);

        $this->postJson("/api/games/{$game->id}/rating")->assertStatus(409)->assertJsonPath('reason', 'rating_auth_required');
        Http::assertNothingSent();

        $this->postJson('/api/quizplease/rating/login', ['username' => 'host@example.com', 'password' => 'secret-pass'])->assertNoContent();
        Http::assertSent(fn (Request $request) => $request->url() === self::LOGIN_URL
            && $request['email'] === 'host@example.com' && $request['password'] === 'secret-pass');
        $this->assertSame('15|rating-secret', $user->fresh()->quizplease_rating_token);
        $this->assertNotSame('15|rating-secret', DB::table('users')->where('id', $user->id)->value('quizplease_rating_token'));
        $this->assertStringNotContainsString('secret-pass', json_encode(DB::table('users')->where('id', $user->id)->first()));

        $this->postJson("/api/games/{$game->id}/rating")->assertOk()->assertJsonPath('status', 'finished');
        Http::assertSent(fn (Request $request) => $request->url() === self::GAME_URL
            && $request->hasHeader('Authorization', 'Bearer rating-secret')
            && $request->data() === [
                'city' => 'pvl',
                'schedule' => ['time' => '19:00', 'date' => '2026-09-30'],
                'rating' => 1,
                'title' => 'Квиз, плиз! PVL',
                'number' => self::EXTERNAL_ID,
                'package' => '',
                'package_number' => '124',
                'is_count' => false,
            ]);
        $this->assertNotNull($game->fresh()->rating_created_at);

        // A created game is not sent again.
        $this->postJson("/api/games/{$game->id}/rating")->assertStatus(409)->assertJsonPath('reason', 'rating_already_created');
        Http::assertSentCount(2);
    }

    public function test_wrong_rating_credentials_store_nothing(): void
    {
        $user = $this->user();
        Http::fake([self::LOGIN_URL => Http::response(['message' => 'Неверные данные'], 422)]);

        $this->actingAs($user)->postJson('/api/quizplease/rating/login', ['username' => 'host@example.com', 'password' => 'bad'])
            ->assertUnprocessable()->assertJsonPath('reason', 'rating_invalid_credentials');
        $this->assertNull($user->fresh()->quizplease_rating_token);
    }

    public function test_rejected_token_is_dropped_and_rating_errors_are_passed_on(): void
    {
        $user = $this->user('15|old');
        $game = $this->readyGame($user);
        $this->actingAs($user);

        Http::fake([self::GAME_URL => Http::sequence()
            ->push(['message' => 'Такая игра уже существует'], 422)
            ->push(['message' => 'Unauthenticated.'], 401)]);

        $this->postJson("/api/games/{$game->id}/rating")->assertUnprocessable()
            ->assertJsonPath('reason', 'rating_rejected')->assertJsonPath('message', 'Такая игра уже существует');
        $this->assertNull($game->fresh()->rating_created_at);

        $this->postJson("/api/games/{$game->id}/rating")->assertStatus(409)->assertJsonPath('reason', 'rating_auth_required');
        $this->assertNull($user->fresh()->quizplease_rating_token);
    }

    public function test_incomplete_or_unfinished_games_are_not_sent(): void
    {
        $user = $this->user('15|token');
        Http::fake();
        $this->actingAs($user);

        $draft = $this->readyGame($user);
        $draft->forceFill(['status' => GameStatus::Draft, 'finished_at' => null])->save();
        $this->postJson("/api/games/{$draft->id}/rating")->assertUnprocessable()->assertJsonPath('reason', 'rating_incomplete');

        $noCategory = $this->readyGame($user, ['external_id' => '01a0a6d4-60fa-715c-b8cf-f2bc6b44dd00', 'rating_category_id' => null]);
        $this->postJson("/api/games/{$noCategory->id}/rating")->assertUnprocessable()->assertJsonPath('reason', 'rating_incomplete');

        Http::assertNothingSent();
    }

    public function test_other_users_cannot_create_the_rating_game(): void
    {
        $game = $this->readyGame($this->user());
        Http::fake();

        $this->actingAs($this->user('15|token'))->postJson("/api/games/{$game->id}/rating")->assertNotFound();
        Http::assertNothingSent();
    }

    private function user(?string $ratingToken = null): User
    {
        $user = User::create(['email' => fake()->unique()->safeEmail(), 'password' => 'test-password']);
        $user->forceFill(['quizplease_rating_token' => $ratingToken, 'quizplease_rating_city' => $ratingToken ? 'pvl' : null])->save();

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
            ...$overrides,
        ])->save();

        return $game;
    }
}
