<?php

namespace App\Services;

use App\Exceptions\QuizPleaseException;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

// Read-only client for the Quiz Please admin API (api.quizplease.com), mirroring admin.quizplease.com.
class QuizPleaseClient
{
    // Record statuses in the admin: 0 unconfirmed, 1 confirmed, 2 no-show, 3 reserve, 4 deleted, 5 awaiting payment.
    private const CONFIRMED = 1;

    public function login(User $user, string $username, string $password): void
    {
        // A missing OAuth client is a server misconfiguration, not a user credentials error.
        if (!config('services.quizplease.client_id') || !config('services.quizplease.client_secret')) {
            throw new RuntimeException('QUIZPLEASE_CLIENT_ID and QUIZPLEASE_CLIENT_SECRET must be set.');
        }

        $response = $this->send(fn () => $this->http()->post('/oauth/token', [
            'username' => $username,
            'password' => $password,
            'client_id' => config('services.quizplease.client_id'),
            'client_secret' => config('services.quizplease.client_secret'),
            'grant_type' => 'password',
            'scope' => '*',
        ]));

        // Passport answers 400 for wrong credentials (checked against the live API).
        if (in_array($response->status(), [400, 401], true)) {
            throw QuizPleaseException::invalidCredentials();
        }

        $token = $this->successful($response)->json('access_token');
        throw_unless(is_string($token) && $token !== '', QuizPleaseException::unavailable());
        $user->forceFill(['quizplease_token' => $token])->save();
    }

    /** @return array{externalId: string, title: string, date: ?string, teams: list<array{externalId: string, name: string}>} */
    public function game(User $user, string $externalId): array
    {
        throw_if($user->quizplease_token === null, QuizPleaseException::authRequired());

        $response = $this->send(fn () => $this->http()->withToken($user->quizplease_token)
            ->get("/api/games/{$externalId}?relationships[]=game_records.game_team"));

        if ($response->status() === 401) {
            // The API offers no confirmed refresh flow, so a rejected token requires a new login.
            $user->forceFill(['quizplease_token' => null])->save();
            throw QuizPleaseException::authRequired();
        }
        throw_if($response->status() === 403, QuizPleaseException::forbidden());
        throw_if($response->status() === 404, QuizPleaseException::notFound());

        $game = $this->successful($response)->json('data');
        throw_unless(is_array($game) && is_string($game['title'] ?? null) && is_array($game['records'] ?? null), QuizPleaseException::unavailable());

        $teams = collect($game['records'])
            // Joined records belong to another record's team, so only top-level confirmed records are teams.
            ->filter(fn (array $record) => (int) ($record['status'] ?? -1) === self::CONFIRMED
                && empty($record['joined_to_record_id'])
                && isset($record['team']['id'])
                && trim((string) ($record['team']['title'] ?? '')) !== '')
            ->unique(fn (array $record) => (string) $record['team']['id'])
            ->map(fn (array $record) => ['externalId' => (string) $record['team']['id'], 'name' => trim($record['team']['title'])])
            ->values()
            ->all();

        // The admin shows games as "{title} #{game_number}"; without the number every game of a series looks the same.
        $number = $game['game_number'] ?? null;
        $title = trim($game['title']).(is_scalar($number) && (string) $number !== '' ? ' #'.$number : '');

        return [
            'externalId' => $externalId,
            'title' => $title,
            'date' => is_string($game['date'] ?? null) ? $game['date'] : null,
            'teams' => $teams,
        ];
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(config('services.quizplease.url'))
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
            throw QuizPleaseException::unavailable();
        }
    }

    private function successful(Response $response): Response
    {
        throw_unless($response->successful(), QuizPleaseException::unavailable());

        return $response;
    }
}
