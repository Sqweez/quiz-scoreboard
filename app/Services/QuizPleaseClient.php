<?php

namespace App\Services;

use App\Exceptions\QuizPleaseException;
use App\Models\User;
use DateTimeImmutable;
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

    // The admin user's profile in the shape the admin sends to the rating module when signing in there.
    /** @return array{name: string, email: string, is_root: bool, cities: list<array{code: string, title: string}>} */
    public function profile(User $user): array
    {
        $profile = $this->authorized($user, '/api/user')->json('data');
        throw_unless(is_string($profile['name'] ?? null) && is_string($profile['email'] ?? null), QuizPleaseException::unavailable());

        return [
            'name' => $profile['name'],
            'email' => $profile['email'],
            'is_root' => ($profile['role']['slug'] ?? null) === 'root',
            'cities' => collect($profile['managed_cities'] ?? [])
                ->map(fn (array $city) => ['code' => (string) $city['slug'], 'title' => (string) $city['title']])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{externalId: string, title: string, date: ?string, teams: list<array{externalId: string, name: string}>,
     *     extra: array{title: string, gameNumber: ?string, packageNumber: ?string, date: ?string}}
     */
    public function game(User $user, string $externalId): array
    {
        $game = $this->authorized($user, "/api/games/{$externalId}?relationships[]=game_records.game_team")->json('data');
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

        $number = $this->optionalString($game['game_number'] ?? null);
        $date = $this->wallClockDate($game['date'] ?? null);
        // The admin shows games as "{title} #{game_number}"; without the number every game of a series looks the same.
        $title = trim($game['title']).($number !== null ? ' #'.$number : '');

        return [
            'externalId' => $externalId,
            'title' => $title,
            'date' => $date,
            'teams' => $teams,
            'extra' => [
                'title' => trim($game['title']),
                'gameNumber' => $number,
                'packageNumber' => $this->optionalString($game['package_number'] ?? null),
                'date' => $date,
            ],
        ];
    }

    private function authorized(User $user, string $path): Response
    {
        throw_if($user->quizplease_token === null, QuizPleaseException::authRequired());

        $response = $this->send(fn () => $this->http()->withToken($user->quizplease_token)->get($path));

        if ($response->status() === 401) {
            // The API offers no confirmed refresh flow, so a rejected token requires a new login.
            $user->forceFill(['quizplease_token' => null])->save();
            throw QuizPleaseException::authRequired();
        }
        throw_if($response->status() === 403, QuizPleaseException::forbidden());
        throw_if($response->status() === 404, QuizPleaseException::notFound());

        return $this->successful($response);
    }

    private function optionalString(mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    // The API sends "dd.mm.yyyy hh:mm" without a time zone and the admin edits it as is (no conversion),
    // so it is kept as the wall-clock time the organiser entered.
    private function wallClockDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!d.m.Y H:i', $value) : false;
        throw_unless($date !== false && $date->format('d.m.Y H:i') === $value, QuizPleaseException::unavailable());

        return $date->format('Y-m-d H:i');
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
