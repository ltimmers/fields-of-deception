<?php

namespace App\Models;

use App\Enums\PieceRank;
use App\Enums\PlayerColor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Move extends Model
{
    protected $fillable = [
        'game_id',
        'player_color',
        'piece_rank',
        'from_row',
        'from_col',
        'to_row',
        'to_col',
        'captured_rank',
        'result',
        'move_number',
    ];

    protected function casts(): array
    {
        return [
            'player_color' => PlayerColor::class,
            'piece_rank' => PieceRank::class,
            'captured_rank' => PieceRank::class,
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
