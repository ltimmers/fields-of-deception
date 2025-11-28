<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GameController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Games
    Route::get('/games', [GameController::class, 'index']);
    Route::get('/games/open', [GameController::class, 'openGames']);
    Route::post('/games', [GameController::class, 'store']);
    Route::get('/games/{game}', [GameController::class, 'show']);
    Route::post('/games/{game}/join', [GameController::class, 'join']);
    Route::post('/games/{game}/setup', [GameController::class, 'setup']);
    Route::post('/games/{game}/move', [GameController::class, 'move']);
    Route::post('/games/{game}/valid-moves', [GameController::class, 'validMoves']);
    Route::post('/games/{game}/forfeit', [GameController::class, 'forfeit']);
});
