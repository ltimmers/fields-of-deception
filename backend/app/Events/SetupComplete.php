<?php

namespace App\Events;

use App\Enums\PlayerColor;
use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SetupComplete implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game,
        public PlayerColor $playerColor
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('game.' . $this->game->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'setup.complete';
    }

    public function broadcastWith(): array
    {
        return [
            'game_id' => $this->game->id,
            'player_color' => $this->playerColor->value,
            'status' => $this->game->status->value,
            'red_setup_complete' => $this->game->red_setup_complete,
            'blue_setup_complete' => $this->game->blue_setup_complete,
        ];
    }
}
