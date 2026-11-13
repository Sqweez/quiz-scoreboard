<?php

use App\Http\Controllers\AuthController;
use App\Models\Game;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('index'));
Route::get('/login', fn () => Inertia::render('login'))->middleware('guest')->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware(['guest', 'throttle:10,1']);
Route::post('/register', [AuthController::class, 'register'])->middleware(['guest', 'throttle:10,1']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
Route::redirect('/game', '/');

Route::middleware('auth')->prefix('games')->group(function () {
    Route::get('/new', fn () => Inertia::render('games/new'));

    Route::middleware('can:manage,game')->group(function () {
        Route::get('/{game}', fn (Game $game) => Inertia::render('games/[id]/index', ['gameId' => $game->id]));
        Route::get('/{game}/edit', fn (Game $game) => Inertia::render('games/[id]/edit', ['gameId' => $game->id]));
    });
});
