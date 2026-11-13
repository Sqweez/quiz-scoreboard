<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuizPleaseGameRequest;
use App\Http\Requests\QuizPleaseLoginRequest;
use App\Models\Game;
use App\Services\QuizPleaseClient;
use App\Services\QuizPleaseRatingClient;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class QuizPleaseController extends Controller
{
    public function __construct(private QuizPleaseClient $client) {}

    public function login(QuizPleaseLoginRequest $request): Response
    {
        $this->client->login($request->user(), $request->validated('username'), $request->validated('password'));

        return response()->noContent();
    }

    public function ratingCategories(QuizPleaseRatingClient $rating): array
    {
        return $rating->categories();
    }

    // Only reads the external game; the local game is created later by the regular store endpoint.
    public function game(QuizPleaseGameRequest $request): array
    {
        $externalId = Str::lower($request->validated('id'));

        return [
            ...$this->client->game($request->user(), $externalId),
            'existingGameId' => Game::where('user_id', $request->user()->id)->where('external_id', $externalId)->value('id'),
        ];
    }
}
