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

class MoveMade implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Game $game,
        public array $result,
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
        return 'move.made';
    }

    public function broadcastWith(): array
    {
        return [
            'game_id' => $this->game->id,
            'result' => $this->result,
            'player_color' => $this->playerColor->value,
            'current_turn' => $this->game->current_turn->value,
            'status' => $this->game->status->value,
        ];
    }
}
