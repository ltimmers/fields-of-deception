<?php

namespace Tests\Unit;

use App\Enums\GameStatus;
use App\Enums\PieceRank;
use App\Enums\PlayerColor;
use App\Models\Game;
use App\Models\User;
use App\Services\GameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private GameService $gameService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gameService = new GameService();
    }

    public function test_execute_move_simple_movement(): void
    {
        $game = $this->createGameWithPiece(PlayerColor::RED, 6, 0, PieceRank::SCOUT);

        $result = $this->gameService->executeMove($game, 6, 0, 5, 0, PlayerColor::RED);

        $this->assertEquals('move', $result['type']);
        $this->assertEquals(PlayerColor::BLUE, $game->current_turn);
        $this->assertEquals(GameStatus::IN_PROGRESS, $game->status);
    }

    public function test_execute_move_attacker_wins(): void
    {
        $game = $this->createGameWithPiece(PlayerColor::RED, 6, 0, PieceRank::MARSHAL);
        $board = $game->board_state;
        $board[5][0] = ['color' => 'blue', 'rank' => PieceRank::SCOUT->value, 'revealed' => false];
        $game->board_state = $board;
        $game->save();

        $result = $this->gameService->executeMove($game, 6, 0, 5, 0, PlayerColor::RED);

        $this->assertEquals('win', $result['type']);
        $this->assertNotNull($result['captured']);
        $this->assertEquals(PieceRank::SCOUT->value, $result['captured']['rank']);
        $this->assertEquals(PlayerColor::BLUE, $game->current_turn);
    }

    public function test_execute_move_attacker_loses(): void
    {
        $game = $this->createGameWithPiece(PlayerColor::RED, 6, 0, PieceRank::SCOUT);
        $board = $game->board_state;
        $board[5][0] = ['color' => 'blue', 'rank' => PieceRank::MARSHAL->value, 'revealed' => false];
        $game->board_state = $board;
        $game->save();

        $result = $this->gameService->executeMove($game, 6, 0, 5, 0, PlayerColor::RED);

        $this->assertEquals('lose', $result['type']);
        $this->assertNotNull($result['captured']);
        $this->assertEquals(PieceRank::SCOUT->value, $result['captured']['rank']);
        $this->assertEquals(PlayerColor::BLUE, $game->current_turn);
    }

    public function test_execute_move_draw(): void
    {
        $game = $this->createGameWithPiece(PlayerColor::RED, 6, 0, PieceRank::SCOUT);
        $board = $game->board_state;
        $board[5][0] = ['color' => 'blue', 'rank' => PieceRank::SCOUT->value, 'revealed' => false];
        $game->board_state = $board;
        $game->save();

        $result = $this->gameService->executeMove($game, 6, 0, 5, 0, PlayerColor::RED);

        $this->assertEquals('draw', $result['type']);
        $this->assertIsArray($result['captured']);
        $this->assertCount(2, $result['captured']);
        $this->assertEquals(PlayerColor::BLUE, $game->current_turn);
    }

    public function test_execute_move_spy_defeats_marshal(): void
    {
        $game = $this->createGameWithPiece(PlayerColor::RED, 6, 0, PieceRank::SPY);
        $board = $game->board_state;
        $board[5][0] = ['color' => 'blue', 'rank' => PieceRank::MARSHAL->value, 'revealed' => false];
        $game->board_state = $board;
        $game->save();

        $result = $this->gameService->executeMove($game, 6, 0, 5, 0, PlayerColor::RED);

        $this->assertEquals('win', $result['type']);
        $this->assertEquals(PieceRank::MARSHAL->value, $result['captured']['rank']);
    }

    public function test_execute_move_miner_defuses_bomb(): void
    {
        $game = $this->createGameWithPiece(PlayerColor::RED, 6, 0, PieceRank::MINER);
        $board = $game->board_state;
        $board[5][0] = ['color' => 'blue', 'rank' => PieceRank::BOMB->value, 'revealed' => false];
        $game->board_state = $board;
        $game->save();

        $result = $this->gameService->executeMove($game, 6, 0, 5, 0, PlayerColor::RED);

        $this->assertEquals('win', $result['type']);
        $this->assertEquals(PieceRank::BOMB->value, $result['captured']['rank']);
    }

    public function test_execute_move_bomb_destroys_non_miner(): void
    {
        $game = $this->createGameWithPiece(PlayerColor::RED, 6, 0, PieceRank::MARSHAL);
        $board = $game->board_state;
        $board[5][0] = ['color' => 'blue', 'rank' => PieceRank::BOMB->value, 'revealed' => false];
        $game->board_state = $board;
        $game->save();

        $result = $this->gameService->executeMove($game, 6, 0, 5, 0, PlayerColor::RED);

        $this->assertEquals('lose', $result['type']);
        $this->assertEquals(PieceRank::MARSHAL->value, $result['captured']['rank']);
    }

    public function test_execute_move_capturing_flag_wins_game(): void
    {
        $game = $this->createGameWithPiece(PlayerColor::RED, 6, 0, PieceRank::SCOUT);
        $board = $game->board_state;
        $board[5][0] = ['color' => 'blue', 'rank' => PieceRank::FLAG->value, 'revealed' => false];
        $game->board_state = $board;
        $game->save();

        $result = $this->gameService->executeMove($game, 6, 0, 5, 0, PlayerColor::RED);

        $this->assertEquals('win', $result['type']);
        $this->assertEquals(PlayerColor::RED, $result['winner']);
        $this->assertEquals(GameStatus::FINISHED, $game->status);
        $this->assertEquals(PlayerColor::RED, $game->winner);
        $this->assertEquals(PlayerColor::RED, $game->current_turn); // Turn should not switch when game ends
    }

    public function test_execute_move_creates_move_record(): void
    {
        $game = $this->createGameWithPiece(PlayerColor::RED, 6, 0, PieceRank::SCOUT);

        $this->gameService->executeMove($game, 6, 0, 5, 0, PlayerColor::RED);

        $this->assertDatabaseHas('moves', [
            'game_id' => $game->id,
            'player_color' => PlayerColor::RED->value,
            'piece_rank' => PieceRank::SCOUT->value,
            'from_row' => 6,
            'from_col' => 0,
            'to_row' => 5,
            'to_col' => 0,
            'move_number' => 1,
        ]);
    }

    public function test_execute_move_increments_move_number(): void
    {
        $game = $this->createGameWithPiece(PlayerColor::RED, 6, 0, PieceRank::SCOUT);

        $this->gameService->executeMove($game, 6, 0, 5, 0, PlayerColor::RED);
        
        // Prepare for second move
        $board = $game->board_state;
        $board[6][1] = ['color' => 'blue', 'rank' => PieceRank::SCOUT->value, 'revealed' => false];
        $game->board_state = $board;
        $game->current_turn = PlayerColor::BLUE;
        $game->save();

        $this->gameService->executeMove($game, 6, 1, 5, 1, PlayerColor::BLUE);

        $this->assertDatabaseHas('moves', [
            'game_id' => $game->id,
            'move_number' => 2,
        ]);
    }

    public function test_get_board_for_player_hides_opponent_unrevealed_pieces(): void
    {
        $game = $this->createGameWithPiece(PlayerColor::RED, 6, 0, PieceRank::SCOUT);
        $board = $game->board_state;
        $board[6][0]['revealed'] = true;
        $board[2][0] = ['color' => 'blue', 'rank' => PieceRank::MARSHAL->value, 'revealed' => false];
        $board[2][1] = ['color' => 'blue', 'rank' => PieceRank::SCOUT->value, 'revealed' => true];
        $game->board_state = $board;
        $game->save();

        $boardForRed = $this->gameService->getBoardForPlayer($board, PlayerColor::RED);

        // Red's pieces should be visible
        $this->assertEquals(PieceRank::SCOUT->value, $boardForRed[6][0]['rank']);
        
        // Blue's revealed pieces should be visible
        $this->assertEquals(PieceRank::SCOUT->value, $boardForRed[2][1]['rank']);
        
        // Blue's unrevealed pieces should be hidden
        $this->assertArrayNotHasKey('rank', $boardForRed[2][0]);
        $this->assertEquals('blue', $boardForRed[2][0]['color']);
    }

    public function test_get_valid_moves_returns_moves_for_player(): void
    {
        $game = $this->createGameWithPiece(PlayerColor::RED, 6, 0, PieceRank::SCOUT);

        $moves = $this->gameService->getValidMoves($game->board_state, PlayerColor::RED);

        $this->assertNotEmpty($moves);
        $this->assertArrayHasKey('from', $moves[0]);
        $this->assertArrayHasKey('to', $moves[0]);
    }

    /**
     * Create a game with a single piece for testing
     */
    private function createGameWithPiece(PlayerColor $color, int $row, int $col, PieceRank $rank): Game
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $game = Game::factory()->inProgress()->create([
            'player_red_id' => $user1->id,
            'player_blue_id' => $user2->id,
            'current_turn' => $color,
        ]);

        $board = $game->board_state;
        $board[$row][$col] = [
            'color' => $color->value,
            'rank' => $rank->value,
            'revealed' => false,
        ];
        $game->board_state = $board;
        $game->save();

        return $game;
    }
}
