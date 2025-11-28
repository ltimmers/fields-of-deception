<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('games', function () {
    return true; // Public channel for game listings
});

Broadcast::channel('game.{gameId}', function ($user, $gameId) {
    $game = \App\Models\Game::find($gameId);

    if (!$game) {
        return false;
    }

    // Allow if user is a player in the game
    return $game->player_red_id === $user->id ||
           $game->player_blue_id === $user->id ||
           $game->is_vs_ai;
});
