<?php

use App\Http\Controllers\GameController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    DB::select('SELECT 1');
    return ['status' => 'ok', 'database' => 'connected'];
});

Route::middleware('auth')->prefix('games')->controller(GameController::class)->group(function () {
    Route::get('/active', 'active');
    Route::get('/finished', 'finished');
    Route::get('/current', 'current');
    Route::post('/', 'store');

    Route::middleware('can:manage,game')->scopeBindings()->group(function () {
        Route::get('/{game}', 'show');
        Route::patch('/{game}', 'update');
        Route::delete('/{game}', 'destroy');
        Route::post('/{game}/finish', 'finish');
        Route::post('/{game}/teams', 'addTeam');
        Route::patch('/{game}/teams/{team}', 'renameTeam');
        Route::delete('/{game}/teams/{team}', 'deleteTeam');
        Route::post('/{game}/rounds', 'addRound');
        Route::patch('/{game}/rounds/{round}', 'updateRound');
        Route::delete('/{game}/rounds/{round}', 'deleteRound');
        Route::patch('/{game}/scores/bulk', 'scores');
        Route::patch('/{game}/scores', 'score');
    });
});
