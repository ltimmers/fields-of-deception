<?php

namespace App\Models;

use App\Enums\GameStatus;
use App\Enums\PlayerColor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_red_id',
        'player_blue_id',
        'status',
        'current_turn',
        'winner',
        'board_state',
        'is_vs_ai',
        'ai_difficulty',
        'red_setup_complete',
        'blue_setup_complete',
    ];

    protected function casts(): array
    {
        return [
            'status' => GameStatus::class,
            'current_turn' => PlayerColor::class,
            'winner' => PlayerColor::class,
            'board_state' => 'array',
            'is_vs_ai' => 'boolean',
            'red_setup_complete' => 'boolean',
            'blue_setup_complete' => 'boolean',
        ];
    }

    public function playerRed(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_red_id');
    }

    public function playerBlue(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_blue_id');
    }

    public function moves(): HasMany
    {
        return $this->hasMany(Move::class);
    }

    public function isPlayerTurn(int $userId): bool
    {
        if ($this->current_turn === PlayerColor::RED) {
            return $this->player_red_id === $userId;
        }
        return $this->player_blue_id === $userId;
    }

    public function getPlayerColor(int $userId): ?PlayerColor
    {
        if ($this->player_red_id === $userId) {
            return PlayerColor::RED;
        }
        if ($this->player_blue_id === $userId) {
            return PlayerColor::BLUE;
        }
        return null;
    }

    public function switchTurn(): void
    {
        $this->current_turn = $this->current_turn === PlayerColor::RED
            ? PlayerColor::BLUE
            : PlayerColor::RED;
    }

    public function isSetupComplete(): bool
    {
        return $this->red_setup_complete && $this->blue_setup_complete;
    }
}
