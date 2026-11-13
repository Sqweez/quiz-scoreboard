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

    public function login(User $user, string $email, string $password): void
    {
        $response = $this->send(fn () => $this->http()->post('/api/auth/login', ['email' => $email, 'password' => $password]));

        if (in_array($response->status(), [401, 422], true)) {
            throw QuizPleaseException::ratingInvalidCredentials();
        }

        // The front keeps the whole user and takes the token and the city code from it.
        $token = $response->json('result.user.token');
        $city = $response->json('result.user.city.code');
        throw_unless($response->successful() && is_string($token) && str_contains($token, '|') && is_string($city) && $city !== '',
            QuizPleaseException::ratingUnavailable());

        $user->forceFill(['quizplease_rating_token' => $token, 'quizplease_rating_city' => $city])->save();
    }

    // Same payload as the "Добавление новой игры" form of the rating front.
    public function createGame(User $user, Game $game): void
    {
        throw_if($user->quizplease_rating_token === null, QuizPleaseException::ratingAuthRequired());

        [$date, $time] = explode(' ', $game->extra['date']);

        // The token is stored as "id|secret"; the front sends only the secret part.
        $response = $this->send(fn () => $this->http()
            ->withToken(explode('|', $user->quizplease_rating_token, 2)[1])
            ->post('/api/game', [
                'city' => $user->quizplease_rating_city,
                'schedule' => ['time' => $time, 'date' => $date],
                'rating' => $game->rating_category_id,
                'title' => $game->extra['title'],
                'number' => $game->external_id,
                'package' => '',
                'package_number' => $game->extra['gameNumber'],
                'is_count' => $game->rating_include_scores,
            ]));

        if ($response->status() === 401) {
            $user->forceFill(['quizplease_rating_token' => null, 'quizplease_rating_city' => null])->save();
            throw QuizPleaseException::ratingAuthRequired();
        }
        if ($response->clientError()) {
            $message = $response->json('message');
            throw QuizPleaseException::ratingRejected(is_string($message) ? $message : null);
        }
        throw_unless($response->successful(), QuizPleaseException::ratingUnavailable());
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
