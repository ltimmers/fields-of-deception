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

class GameServiceTest extends TestCase
{
    use RefreshDatabase;

    private GameService $gameService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gameService = new GameService();
    }

    public function test_creates_empty_board_with_correct_dimensions(): void
    {
        $board = $this->gameService->createEmptyBoard();

        $this->assertIsArray($board);
        $this->assertCount(10, $board);
        
        foreach ($board as $row) {
            $this->assertCount(10, $row);
        }
    }

    public function test_creates_board_with_lakes_in_correct_positions(): void
    {
        $board = $this->gameService->createEmptyBoard();

        $expectedLakes = [
            [4, 2], [4, 3], [5, 2], [5, 3],
            [4, 6], [4, 7], [5, 6], [5, 7],
        ];

        foreach ($expectedLakes as $lake) {
            $this->assertEquals(
                ['type' => 'lake'],
                $board[$lake[0]][$lake[1]],
                "Expected lake at position [{$lake[0]}, {$lake[1]}]"
            );
        }
    }

    public function test_is_lake_returns_true_for_lake_positions(): void
    {
        $this->assertTrue($this->gameService->isLake(4, 2));
        $this->assertTrue($this->gameService->isLake(4, 3));
        $this->assertTrue($this->gameService->isLake(5, 2));
        $this->assertTrue($this->gameService->isLake(5, 3));
        $this->assertTrue($this->gameService->isLake(4, 6));
        $this->assertTrue($this->gameService->isLake(4, 7));
        $this->assertTrue($this->gameService->isLake(5, 6));
        $this->assertTrue($this->gameService->isLake(5, 7));
    }

    public function test_is_lake_returns_false_for_non_lake_positions(): void
    {
        $this->assertFalse($this->gameService->isLake(0, 0));
        $this->assertFalse($this->gameService->isLake(5, 5));
        $this->assertFalse($this->gameService->isLake(9, 9));
    }

    public function test_validate_setup_accepts_valid_red_setup(): void
    {
        $pieces = $this->generateValidSetup(PlayerColor::RED);
        $result = $this->gameService->validateSetup($pieces, PlayerColor::RED);

        $this->assertTrue($result);
    }

    public function test_validate_setup_accepts_valid_blue_setup(): void
    {
        $pieces = $this->generateValidSetup(PlayerColor::BLUE);
        $result = $this->gameService->validateSetup($pieces, PlayerColor::BLUE);

        $this->assertTrue($result);
    }

    public function test_validate_setup_rejects_wrong_piece_count(): void
    {
        $pieces = array_map(fn($i) => [
            'row' => 6,
            'col' => $i,
            'rank' => 2,
        ], range(0, 9));

        $result = $this->gameService->validateSetup($pieces, PlayerColor::RED);

        $this->assertFalse($result);
    }

    public function test_validate_setup_rejects_pieces_in_wrong_rows_for_red(): void
    {
        $pieces = $this->generateValidSetup(PlayerColor::RED);
        // Move a piece to an invalid row
        $pieces[0]['row'] = 5;

        $result = $this->gameService->validateSetup($pieces, PlayerColor::RED);

        $this->assertFalse($result);
    }

    public function test_validate_setup_rejects_pieces_in_wrong_rows_for_blue(): void
    {
        $pieces = $this->generateValidSetup(PlayerColor::BLUE);
        // Move a piece to an invalid row
        $pieces[0]['row'] = 4;

        $result = $this->gameService->validateSetup($pieces, PlayerColor::BLUE);

        $this->assertFalse($result);
    }

    public function test_validate_setup_rejects_pieces_on_lakes(): void
    {
        $pieces = $this->generateValidSetup(PlayerColor::RED);
        // Try to place a piece on a lake (but lakes are at rows 4-5, so this wouldn't naturally happen)
        // Instead, let's modify the setup to have wrong piece counts
        $pieces[0]['row'] = 4;
        $pieces[0]['col'] = 3
        ;
        $result = $this->gameService->validateSetup($pieces, PlayerColor::RED);

        $this->assertFalse($result);
    }

    public function test_validate_setup_rejects_incorrect_piece_distribution(): void
    {
        $pieces = $this->generateValidSetup(PlayerColor::RED);
        // Change a piece rank to create an invalid distribution
        $pieces[1]['rank'] = 10; // Create a second marshal

        $result = $this->gameService->validateSetup($pieces, PlayerColor::RED);

        $this->assertFalse($result);
    }

    public function test_place_pieces_adds_pieces_to_board(): void
    {
        $board = $this->gameService->createEmptyBoard();
        $pieces = [
            ['row' => 6, 'col' => 0, 'rank' => 2],
            ['row' => 6, 'col' => 1, 'rank' => 3],
        ];

        $result = $this->gameService->placePieces($board, $pieces, PlayerColor::RED);

        $this->assertEquals([
            'rank' => 2,
            'color' => 'red',
            'revealed' => false,
        ], $result[6][0]);

        $this->assertEquals([
            'rank' => 3,
            'color' => 'red',
            'revealed' => false,
        ], $result[6][1]);
    }

    public function test_validate_move_rejects_moving_empty_square(): void
    {
        $game = $this->createGameWithBoard();

        $result = $this->gameService->validateMove(
            $game,
            6, 0, // from empty square
            5, 0,
            PlayerColor::RED
        );

        $this->assertFalse($result);
    }

    public function test_validate_move_rejects_moving_opponent_piece(): void
    {
        $game = $this->createGameWithBoard();
        $board = $game->board_state;
        $board[6][0] = ['color' => 'blue', 'rank' => 2];
        $game->board_state = $board;
        $game->save();

        $result = $this->gameService->validateMove(
            $game,
            6, 0,
            5, 0,
            PlayerColor::RED
        );

        $this->assertFalse($result);
    }

    public function test_validate_move_rejects_moving_flag(): void
    {
        $game = $this->createGameWithBoard();
        $board = $game->board_state;
        $board[6][0] = ['color' => 'red', 'rank' => 0]; // Flag
        $game->board_state = $board;
        $game->save();

        $result = $this->gameService->validateMove(
            $game,
            6, 0,
            5, 0,
            PlayerColor::RED
        );

        $this->assertFalse($result);
    }

    public function test_validate_move_rejects_moving_bomb(): void
    {
        $game = $this->createGameWithBoard();
        $board = $game->board_state;
        $board[6][0] = ['color' => 'red', 'rank' => 11]; // Bomb
        $game->board_state = $board;
        $game->save();

        $result = $this->gameService->validateMove(
            $game,
            6, 0,
            5, 0,
            PlayerColor::RED
        );

        $this->assertFalse($result);
    }

    public function test_validate_move_rejects_moving_to_lake(): void
    {
        $game = $this->createGameWithBoard();
        $board = $game->board_state;
        $board[5][2] = ['color' => 'red', 'rank' => 2];
        $game->board_state = $board;
        $game->save();

        $result = $this->gameService->validateMove(
            $game,
            5, 2,
            4, 2, // Lake position
            PlayerColor::RED
        );

        $this->assertFalse($result);
    }

    public function test_validate_move_rejects_moving_to_own_piece(): void
    {
        $game = $this->createGameWithBoard();
        $board = $game->board_state;
        $board[6][0] = ['color' => 'red', 'rank' => 2];
        $board[5][0] = ['color' => 'red', 'rank' => 3];
        $game->board_state = $board;
        $game->save();

        $result = $this->gameService->validateMove(
            $game,
            6, 0,
            5, 0,
            PlayerColor::RED
        );

        $this->assertFalse($result);
    }

    public function test_validate_move_accepts_valid_one_square_move(): void
    {
        $game = $this->createGameWithBoard();
        $board = $game->board_state;
        $board[6][0] = ['color' => 'red', 'rank' => 3]; // Miner
        $game->board_state = $board;
        $game->save();

        $result = $this->gameService->validateMove(
            $game,
            6, 0,
            5, 0,
            PlayerColor::RED
        );

        $this->assertTrue($result);
    }

    public function test_validate_move_rejects_diagonal_move(): void
    {
        $game = $this->createGameWithBoard();
        $board = $game->board_state;
        $board[6][0] = ['color' => 'red', 'rank' => 3];
        $game->board_state = $board;
        $game->save();

        $result = $this->gameService->validateMove(
            $game,
            6, 0,
            5, 1, // Diagonal
            PlayerColor::RED
        );

        $this->assertFalse($result);
    }

    public function test_validate_move_accepts_scout_multi_square_move(): void
    {
        $game = $this->createGameWithBoard();
        $board = $game->board_state;
        $board[6][0] = ['color' => 'red', 'rank' => 2]; // Scout
        $game->board_state = $board;
        $game->save();

        $result = $this->gameService->validateMove(
            $game,
            6, 0,
            3, 0, // Move 3 squares
            PlayerColor::RED
        );

        $this->assertTrue($result);
    }

    public function test_validate_move_rejects_scout_move_through_piece(): void
    {
        $game = $this->createGameWithBoard();
        $board = $game->board_state;
        $board[6][0] = ['color' => 'red', 'rank' => 2]; // Scout
        $board[5][0] = ['color' => 'blue', 'rank' => 3]; // Blocking piece
        $game->board_state = $board;
        $game->save();

        $result = $this->gameService->validateMove(
            $game,
            6, 0,
            4, 0, // Try to move through the blocking piece
            PlayerColor::RED
        );

        $this->assertFalse($result);
    }

    public function test_has_movable_pieces_returns_true_when_pieces_can_move(): void
    {
        $board = $this->gameService->createEmptyBoard();
        $board[6][0] = ['color' => 'red', 'rank' => 2]; // Scout can move

        $result = $this->gameService->hasMovablePieces($board, PlayerColor::RED);

        $this->assertTrue($result);
    }

    public function test_has_movable_pieces_returns_false_when_no_movable_pieces(): void
    {
        $board = $this->gameService->createEmptyBoard();
        $board[6][0] = ['color' => 'red', 'rank' => 0]; // Flag cannot move
        $board[6][1] = ['color' => 'red', 'rank' => 11]; // Bomb cannot move

        $result = $this->gameService->hasMovablePieces($board, PlayerColor::RED);

        $this->assertFalse($result);
    }

    public function test_has_movable_pieces_returns_false_when_all_pieces_blocked(): void
    {
        $board = $this->gameService->createEmptyBoard();
        // Surround a movable piece with other pieces
        $board[5][5] = ['color' => 'red', 'rank' => 2]; // Scout
        $board[4][5] = ['color' => 'red', 'rank' => 11]; // Block north with a bomb
        $board[6][5] = ['color' => 'red', 'rank' => 11]; // Block south with a bomb
        $board[5][4] = ['color' => 'red', 'rank' => 11]; // Block west with a bomb
        $board[5][6] = ['color' => 'red', 'rank' => 11]; // Block east with a bomb

        $result = $this->gameService->hasMovablePieces($board, PlayerColor::RED);

        $this->assertFalse($result);
    }

    /**
     * Generate a valid piece setup for testing
     */
    private function generateValidSetup(PlayerColor $color): array
    {
        $pieces = [];
        $startRow = $color === PlayerColor::RED ? 6 : 0;
        
        $ranks = [
            10 => 1, 9 => 1, 8 => 2, 7 => 3, 6 => 4,
            5 => 4, 4 => 4, 3 => 5, 2 => 8, 1 => 1,
            11 => 6, 0 => 1,
        ];

        $row = $startRow;
        $col = 0;

        foreach ($ranks as $rank => $count) {
            for ($i = 0; $i < $count; $i++) {
                $pieces[] = [
                    'row' => $row,
                    'col' => $col,
                    'rank' => $rank,
                ];

                $col++;
                if ($col >= 10) {
                    $col = 0;
                    $row++;
                }
            }
        }

        return $pieces;
    }

    /**
     * Create a game with an empty board for testing
     */
    private function createGameWithBoard(): Game
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        return Game::factory()->inProgress()->create([
            'player_red_id' => $user1->id,
            'player_blue_id' => $user2->id,
            'current_turn' => PlayerColor::RED,
        ]);
    }
}
