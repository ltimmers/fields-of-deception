<?php

namespace Tests\Unit;

use App\Enums\PieceRank;
use App\Enums\PlayerColor;
use App\Models\Game;
use App\Models\Move;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoveModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_move_belongs_to_game(): void
    {
        $game = Game::factory()->create();
        $move = Move::create([
            'game_id' => $game->id,
            'player_color' => PlayerColor::RED,
            'piece_rank' => PieceRank::SCOUT,
            'from_row' => 6,
            'from_col' => 0,
            'to_row' => 5,
            'to_col' => 0,
            'captured_rank' => null,
            'result' => 'move',
            'move_number' => 1,
        ]);

        $this->assertInstanceOf(Game::class, $move->game);
        $this->assertEquals($game->id, $move->game->id);
    }

    public function test_move_has_correct_casts(): void
    {
        $game = Game::factory()->create();
        $move = Move::create([
            'game_id' => $game->id,
            'player_color' => PlayerColor::RED,
            'piece_rank' => PieceRank::SCOUT,
            'from_row' => 6,
            'from_col' => 0,
            'to_row' => 5,
            'to_col' => 0,
            'captured_rank' => PieceRank::MINER,
            'result' => 'win',
            'move_number' => 1,
        ]);

        $this->assertInstanceOf(PlayerColor::class, $move->player_color);
        $this->assertInstanceOf(PieceRank::class, $move->piece_rank);
        $this->assertInstanceOf(PieceRank::class, $move->captured_rank);
    }

    public function test_move_can_be_created_with_all_fields(): void
    {
        $game = Game::factory()->create();
        $moveData = [
            'game_id' => $game->id,
            'player_color' => PlayerColor::RED,
            'piece_rank' => PieceRank::SCOUT,
            'from_row' => 6,
            'from_col' => 0,
            'to_row' => 5,
            'to_col' => 0,
            'captured_rank' => PieceRank::MINER,
            'result' => 'win',
            'move_number' => 1,
        ];

        $move = Move::create($moveData);

        $this->assertDatabaseHas('moves', [
            'game_id' => $game->id,
            'player_color' => PlayerColor::RED->value,
            'piece_rank' => PieceRank::SCOUT->value,
            'from_row' => 6,
            'from_col' => 0,
            'to_row' => 5,
            'to_col' => 0,
            'captured_rank' => PieceRank::MINER->value,
            'result' => 'win',
            'move_number' => 1,
        ]);
    }

    public function test_move_captured_rank_can_be_null(): void
    {
        $game = Game::factory()->create();
        $move = Move::create([
            'game_id' => $game->id,
            'player_color' => PlayerColor::RED,
            'piece_rank' => PieceRank::SCOUT,
            'from_row' => 6,
            'from_col' => 0,
            'to_row' => 5,
            'to_col' => 0,
            'captured_rank' => null,
            'result' => 'move',
            'move_number' => 1,
        ]);

        $this->assertNull($move->captured_rank);
    }
}
