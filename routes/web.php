<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('index'));
Route::get('/login', fn () => Inertia::render('login'))->middleware('guest')->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware(['guest', 'throttle:10,1']);
Route::post('/register', [AuthController::class, 'register'])->middleware(['guest', 'throttle:10,1']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
Route::redirect('/game', '/');
Route::get('/api/health', function () {
    DB::select('SELECT 1');
    return ['status' => 'ok', 'database' => 'connected'];
});

Route::middleware('auth')->group(function () {
    Route::get('/games/new', fn () => Inertia::render('games/new'));
    Route::get('/games/{id}/edit', fn (string $id) => Inertia::render('games/[id]/edit', ['gameId' => $id]))->whereUuid('id');
    Route::get('/games/{id}', fn (string $id) => Inertia::render('games/[id]/index', ['gameId' => $id]))->whereUuid('id');

    Route::prefix('api/games')->controller(GameController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/current', 'current');
        Route::get('/{id}', 'show')->whereUuid('id');
        Route::patch('/{id}', 'update')->whereUuid('id');
        Route::delete('/{id}', 'destroy')->whereUuid('id');
        Route::post('/{id}/finish', 'finish')->whereUuid('id');
        Route::post('/{id}/teams', 'addTeam')->whereUuid('id');
        Route::patch('/{id}/teams/{teamId}', 'renameTeam')->whereUuid(['id', 'teamId']);
        Route::delete('/{id}/teams/{teamId}', 'deleteTeam')->whereUuid(['id', 'teamId']);
        Route::post('/{id}/rounds', 'addRound')->whereUuid('id');
        Route::patch('/{id}/rounds/{roundId}', 'updateRound')->whereUuid(['id', 'roundId']);
        Route::delete('/{id}/rounds/{roundId}', 'deleteRound')->whereUuid(['id', 'roundId']);
        Route::patch('/{id}/scores/bulk', 'scores')->whereUuid('id');
        Route::patch('/{id}/scores', 'score')->whereUuid('id');
    });
});
