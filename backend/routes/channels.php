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

    // Allow only players in the game. AI games are owned by the red player.
    return $game->player_red_id === $user->id ||
           $game->player_blue_id === $user->id;
});
