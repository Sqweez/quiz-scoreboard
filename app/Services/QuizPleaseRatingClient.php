<?php

namespace App\Services;

use App\Exceptions\QuizPleaseException;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

// Client for the Quiz Please rating API (rating-api.quizplease.ru), mirroring the rating.quizplease.ru front.
// Its accounts and tokens are separate from the admin API (api.quizplease.com).
class QuizPleaseRatingClient
{
    /** @return list<array{id: int, title: string, code: string}> */
    public function categories(): array
    {
        // The list is public and rarely changes; caching keeps game saves independent of every rating API call.
        return Cache::remember('quizplease.rating.categories', now()->addHour(), function () {
            $response = $this->send(fn () => $this->http()->get('/api/rating'));
            $categories = $response->json();
            throw_unless($response->successful() && is_array($categories), QuizPleaseException::ratingUnavailable());

            return collect($categories)
                ->filter(fn ($category) => is_int($category['id'] ?? null) && is_string($category['title'] ?? null))
                ->map(fn (array $category) => ['id' => $category['id'], 'title' => $category['title'], 'code' => (string) ($category['code'] ?? '')])
                ->values()
                ->all();
        });
    }

    public function __construct(private QuizPleaseClient $admin) {}

    // Creates the game unless the rating already lists it for the city. The rating API cannot search by number and lists
    // only part of the city's games (those the front offers for result upload), so this guards the common repeat only.
    public function createGame(User $user, Game $game): void
    {
        $exists = collect($this->cityGames($user))->contains(fn (array $ratingGame) => ($ratingGame['number'] ?? null) === $game->external_id);

        if ($exists) {
            return;
        }

        $response = $this->authorized($user, fn (PendingRequest $http) => $http->post('/api/game', $this->gamePayload($user, $game)));

        if ($response->clientError()) {
            $message = $response->json('message');
            throw QuizPleaseException::ratingRejected(is_string($message) ? $message : null);
        }
        throw_unless($response->successful(), QuizPleaseException::ratingUnavailable());
    }

    // Queues the results table for processing, like the "Загрузка списка игр" page of the rating front.
    // The rating finds the game by the file name, which must be the game's external id.
    public function queueResults(User $user, Game $game, string $path): void
    {
        $response = $this->authorized($user, fn (PendingRequest $http) => $http
            ->attach('files[]', file_get_contents($path), "{$game->external_id}.xlsx")
            ->post('/api/result/queue', ['city' => $user->quizplease_rating_city_id]));

        if ($response->clientError()) {
            $message = $response->json('message');
            throw QuizPleaseException::ratingRejected(is_string($message) ? $message : null);
        }
        throw_unless($response->successful(), QuizPleaseException::ratingUnavailable());
    }

    // Package names already used by the city's games; the rating API has no package list of its own.
    /** @return list<string> */
    public function packages(User $user): array
    {
        return collect($this->cityGames($user))
            ->map(fn (array $ratingGame) => $ratingGame['package_entity']['title'] ?? null)
            ->filter(fn ($title) => is_string($title) && trim($title) !== '')
            ->map(fn (string $title) => trim($title))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function cityGames(User $user): array
    {
        $response = $this->authorized($user, fn (PendingRequest $http) => $http->get('/api/game', ['city' => $user->quizplease_rating_city_id]));
        $games = $response->json('result');
        throw_unless($response->successful() && is_array($games), QuizPleaseException::ratingUnavailable());

        return array_values(array_filter($games, 'is_array'));
    }

    // Sends a request with the user's rating token, signing in first when there is none and once more when it expired.
    private function authorized(User $user, callable $request): Response
    {
        if ($user->quizplease_rating_token === null) {
            $this->signIn($user);
        }

        // The token is stored as "id|secret"; the front sends only the secret part.
        $send = fn () => $this->send(fn () => $request($this->http()->withToken(explode('|', $user->quizplease_rating_token, 2)[1])));
        $response = $send();

        if ($response->status() === 401) {
            $this->signIn($user);
            $response = $send();
        }

        return $response;
    }

    // Same sign-in as the "Рейтинг" page of admin.quizplease.com: the admin profile is synchronised to the rating
    // module for a one-time token, which the rating front exchanges for its own token in loginByToken.
    private function signIn(User $user): void
    {
        // A missing key is a server misconfiguration, not something the user can fix.
        $key = config('services.quizplease.rating_synchronize_key')
            ?: throw new RuntimeException('QUIZPLEASE_RATING_SYNCHRONIZE_KEY must be set.');
        $profile = $this->admin->profile($user);

        $synchronized = $this->send(fn () => $this->http()->withHeaders(['SynchronizeKey' => $key])
            ->post('/api/synchronize/auth/create', $profile));
        // A rejected key means it was rotated on their side and the server configuration has to be updated.
        throw_if(in_array($synchronized->status(), [401, 403], true), new RuntimeException('The rating module rejected QUIZPLEASE_RATING_SYNCHRONIZE_KEY.'));
        $oneTimeToken = $synchronized->json('user_token.token');
        throw_unless($synchronized->successful() && is_string($oneTimeToken) && $oneTimeToken !== '', QuizPleaseException::ratingUnavailable());

        $login = $this->send(fn () => $this->http()->post('/api/auth/loginByToken', ['token' => $oneTimeToken]));
        $token = $login->json('result.user.token');
        $city = $login->json('result.user.city');
        throw_unless($login->successful() && is_string($token) && str_contains($token, '|')
            && is_int($city['id'] ?? null) && is_string($city['code'] ?? null) && $city['code'] !== '',
            QuizPleaseException::ratingUnavailable());

        // The game form sends the city code, the game list filters by the city id.
        $user->forceFill([
            'quizplease_rating_token' => $token,
            'quizplease_rating_city' => $city['code'],
            'quizplease_rating_city_id' => $city['id'],
        ])->save();
    }

    // Same payload as the "Добавление новой игры" form of the rating front.
    private function gamePayload(User $user, Game $game): array
    {
        [$date, $time] = explode(' ', $game->extra['date']);

        return [
            'city' => $user->quizplease_rating_city,
            'schedule' => ['time' => $time, 'date' => $date],
            'rating' => $game->rating_category_id,
            'title' => $game->extra['title'],
            'number' => $game->external_id,
            'package' => $game->rating_package,
            'package_number' => $game->extra['gameNumber'],
            'is_count' => $game->rating_include_scores,
        ];
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(config('services.quizplease.rating_url'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(config('services.quizplease.timeout'));
    }

    // Timeouts and network failures surface as an unavailable service.
    private function send(callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException) {
            throw QuizPleaseException::ratingUnavailable();
        }
    }
}
