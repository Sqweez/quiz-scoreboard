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
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class QuizPleaseRatingGameTest extends TestCase
{
    use RefreshDatabase;

    private const EXTERNAL_ID = '01a0a6d4-60fa-715c-b8cf-f2bc6b44ddbf';
    private const PROFILE_URL = 'https://api.quizplease.com/api/user';
    private const SYNC_URL = 'https://rating-api.quizplease.ru/api/synchronize/auth/create';
    private const LOGIN_BY_TOKEN_URL = 'https://rating-api.quizplease.ru/api/auth/loginByToken';
    private const GAME_URL = 'https://rating-api.quizplease.ru/api/game';
    private const QUEUE_URL = 'https://rating-api.quizplease.ru/api/result/queue';

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
            self::QUEUE_URL => Http::response(['message' => 'Файлы поставлены в очередь']),
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
        // The rating matches the results to the game by the file name.
        Http::assertSent(fn (Request $request) => $request->url() === self::QUEUE_URL && $request->isMultipart()
            && $request->hasHeader('Authorization', 'Bearer rating-secret')
            && $request->hasFile('files[]', null, self::EXTERNAL_ID.'.xlsx')
            && (string) collect($request->data())->firstWhere('name', 'city')['contents'] === '91');
        $this->assertNotNull($game->fresh()->rating_created_at);
        $this->assertNotNull($game->fresh()->rating_uploaded_at);

        // Uploaded results are not sent again.
        $this->postJson("/api/games/{$game->id}/rating")->assertStatus(409)->assertJsonPath('reason', 'rating_already_uploaded');
        Http::assertSentCount(6);
    }

    public function test_failed_upload_is_retried_without_creating_the_game_again(): void
    {
        $user = $this->user(ratingToken: '15|token');
        $game = $this->readyGame($user);
        Http::fake([
            self::GAME_URL.'*' => $this->ratingGames([]),
            self::QUEUE_URL => Http::sequence()->push('down', 503)->push(['message' => 'ok']),
        ]);
        $this->actingAs($user);

        $this->postJson("/api/games/{$game->id}/rating")->assertStatus(502)->assertJsonPath('reason', 'rating_unavailable');
        $this->assertNotNull($game->fresh()->rating_created_at);
        $this->assertNull($game->fresh()->rating_uploaded_at);

        $this->postJson("/api/games/{$game->id}/rating")->assertOk();
        $this->assertNotNull($game->fresh()->rating_uploaded_at);
        $this->assertCount(1, Http::recorded(fn (Request $request) => $request->method() === 'POST' && $request->url() === self::GAME_URL));
        $this->assertCount(2, Http::recorded(fn (Request $request) => $request->url() === self::QUEUE_URL));
    }

    public function test_rating_game_can_be_only_created_and_the_table_downloaded_for_a_manual_upload(): void
    {
        $user = $this->user(ratingToken: '15|token');
        $game = $this->readyGame($user);
        Http::fake([self::GAME_URL.'*' => $this->ratingGames([]), self::QUEUE_URL => Http::response(['message' => 'ok'])]);
        $this->actingAs($user);

        $this->postJson("/api/games/{$game->id}/rating/game")->assertOk()->assertJsonPath('ratingUploadedAt', null);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request->url() === self::GAME_URL);
        Http::assertNotSent(fn (Request $request) => $request->url() === self::QUEUE_URL);
        $this->assertNotNull($game->fresh()->rating_created_at);
        $this->postJson("/api/games/{$game->id}/rating/game")->assertStatus(409)->assertJsonPath('reason', 'rating_already_created');

        $download = $this->get("/api/games/{$game->id}/rating/results")->assertOk()
            ->assertDownload(self::EXTERNAL_ID.'.xlsx');
        $this->assertStringStartsWith('PK', file_get_contents($download->baseResponse->getFile()->getPathname()));

        // The full upload after a manual creation only queues the table.
        $this->postJson("/api/games/{$game->id}/rating")->assertOk();
        $this->assertCount(1, Http::recorded(fn (Request $request) => $request->method() === 'POST' && $request->url() === self::GAME_URL));
        Http::assertSent(fn (Request $request) => $request->url() === self::QUEUE_URL);
    }

    public function test_uploaded_results_table_matches_the_score_table(): void
    {
        $user = $this->user(ratingToken: '15|token');
        Http::fake([self::GAME_URL.'*' => $this->ratingGames([]), self::QUEUE_URL => Http::response(['message' => 'ok'])]);
        $this->actingAs($user);
        $created = $this->postJson('/api/games', ['title' => 'Q', 'teamNames' => ['Совы', 'Палата№6', 'Ёжики'], 'rounds' => [['title' => 'Разминка'], ['title' => 'Финал']]])
            ->assertCreated()->json();
        [$owls, $ward, $hedgehogs] = array_column($created['teams'], 'id');
        [$first, $second] = array_column($created['rounds'], 'id');
        $this->patchJson("/api/games/{$created['id']}/scores/bulk", ['updates' => [
            ['teamId' => $owls, 'roundId' => $first, 'score' => 6], ['teamId' => $owls, 'roundId' => $second, 'score' => 4],
            ['teamId' => $ward, 'roundId' => $first, 'score' => 5.5], ['teamId' => $ward, 'roundId' => $second, 'score' => 6],
            ['teamId' => $hedgehogs, 'roundId' => $first, 'score' => 4], ['teamId' => $hedgehogs, 'roundId' => $second, 'score' => 6],
        ]])->assertOk();
        $game = Game::findOrFail($created['id']);
        $game->forceFill([
            'status' => GameStatus::Finished, 'finished_at' => now(), 'external_id' => self::EXTERNAL_ID, 'rating_category_id' => 1, 'rating_package' => 'классика',
            'extra' => ['title' => 'Квиз, плиз! PVL', 'gameNumber' => '124', 'packageNumber' => '#124квизплиз', 'date' => '2026-09-30 19:00'],
        ])->save();

        $this->postJson("/api/games/{$game->id}/rating")->assertOk();

        $upload = Http::recorded(fn (Request $request) => $request->url() === self::QUEUE_URL)->first()[0];
        $path = tempnam(sys_get_temp_dir(), 'uploaded');
        file_put_contents($path, collect($upload->data())->firstWhere('name', 'files[]')['contents']);
        $reader = new Reader();
        $reader->open($path);
        foreach ($reader->getSheetIterator() as $sheet) {
            $this->assertSame('Результаты', $sheet->getName());
            $rows = array_map(fn ($row) => $row->toArray(), iterator_to_array($sheet->getRowIterator(), false));
        }
        $reader->close();
        unlink($path);

        // Equal totals are ordered by the latest round, like the score table.
        $this->assertSame([
            ['Место', 'Название команды', 'Итого', 'Раунд 1', 'Раунд 2'],
            [1, 'Палата№6', 11.5, 5.5, 6],
            [2, 'Ёжики', 10, 4, 6],
            [3, 'Совы', 10, 6, 4],
        ], $rows);
    }

    public function test_game_already_listed_in_the_rating_is_marked_without_creating_a_duplicate(): void
    {
        $user = $this->user(ratingToken: '15|token');
        $game = $this->readyGame($user);
        Http::fake([
            self::GAME_URL.'*' => $this->ratingGames([$this->ratingGame(self::EXTERNAL_ID, 'классика')]),
            self::QUEUE_URL => Http::response(['message' => 'ok']),
        ]);

        $this->actingAs($user)->postJson("/api/games/{$game->id}/rating")->assertOk();

        Http::assertNotSent(fn (Request $request) => $request->method() === 'POST' && $request->url() === self::GAME_URL);
        Http::assertSent(fn (Request $request) => $request->url() === self::QUEUE_URL);
        $this->assertNotNull($game->fresh()->rating_uploaded_at);
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
        $this->postJson("/api/games/{$noPackage->id}/rating/game")->assertUnprocessable()->assertJsonPath('reason', 'rating_incomplete');
        $this->getJson("/api/games/{$noPackage->id}/rating/results")->assertUnprocessable()->assertJsonPath('reason', 'rating_incomplete');

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
