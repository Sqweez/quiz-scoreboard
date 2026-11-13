<?php

namespace App\Services;

use App\Exceptions\QuizPleaseException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

// Read-only client for the public part of the Quiz Please rating API (rating-api.quizplease.ru).
class QuizPleaseRatingClient
{
    /** @return list<array{id: int, title: string, code: string}> */
    public function categories(): array
    {
        // The list is public and rarely changes; caching keeps game saves independent of every rating API call.
        return Cache::remember('quizplease.rating.categories', now()->addHour(), function () {
            try {
                $response = Http::baseUrl(config('services.quizplease.rating_url'))->acceptJson()
                    ->connectTimeout(5)->timeout(config('services.quizplease.timeout'))->get('/api/rating');
            } catch (ConnectionException) {
                throw QuizPleaseException::ratingUnavailable();
            }

            $categories = $response->json();
            throw_unless($response->successful() && is_array($categories), QuizPleaseException::ratingUnavailable());

            return collect($categories)
                ->filter(fn ($category) => is_int($category['id'] ?? null) && is_string($category['title'] ?? null))
                ->map(fn (array $category) => ['id' => $category['id'], 'title' => $category['title'], 'code' => (string) ($category['code'] ?? '')])
                ->values()
                ->all();
        });
    }
}
