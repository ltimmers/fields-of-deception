<?php

namespace App\Services;

use App\Enums\GameStatus;
use App\Enums\PieceRank;
use App\Enums\PlayerColor;
use App\Models\Game;
use App\Models\Move;

class GameService
{
    public const BOARD_SIZE = 10;
    public const LAKE_POSITIONS = [
        [4, 2], [4, 3], [5, 2], [5, 3],
        [4, 6], [4, 7], [5, 6], [5, 7],
    ];

    /**
     * Create an empty board with lakes
     */
    public function createEmptyBoard(): array
    {
        $board = [];
        for ($row = 0; $row < self::BOARD_SIZE; $row++) {
            $board[$row] = [];
            for ($col = 0; $col < self::BOARD_SIZE; $col++) {
                if ($this->isLake($row, $col)) {
                    $board[$row][$col] = ['type' => 'lake'];
                } else {
                    $board[$row][$col] = null;
                }
            }
        }
        return $board;
    }

    /**
     * Check if a position is a lake
     */
    public function isLake(int $row, int $col): bool
    {
        foreach (self::LAKE_POSITIONS as $lake) {
            if ($lake[0] === $row && $lake[1] === $col) {
                return true;
            }
        }
        return false;
    }

    /**
     * Validate piece placement during setup phase
     */
    public function validateSetup(array $pieces, PlayerColor $color): bool
    {
        $counts = [];
        foreach (PieceRank::cases() as $rank) {
            $counts[$rank->value] = 0;
        }

        // Red places on rows 6-9, Blue places on rows 0-3
        $validRowRange = $color === PlayerColor::RED ? [6, 9] : [0, 3];

        foreach ($pieces as $piece) {
            $row = $piece['row'];
            $col = $piece['col'];
            $rank = $piece['rank'];

            // Validate row range
            if ($row < $validRowRange[0] || $row > $validRowRange[1]) {
                return false;
            }

            // Validate column range
            if ($col < 0 || $col >= self::BOARD_SIZE) {
                return false;
            }

            // Check not on lake
            if ($this->isLake($row, $col)) {
                return false;
            }

            $counts[$rank]++;
        }

        // Validate piece counts
        foreach (PieceRank::cases() as $rank) {
            if ($counts[$rank->value] !== $rank->getCount()) {
                return false;
            }
        }

        return count($pieces) === 40; // Each player has 40 pieces
    }

    /**
     * Place pieces on the board
     */
    public function placePieces(array $board, array $pieces, PlayerColor $color): array
    {
        foreach ($pieces as $piece) {
            $board[$piece['row']][$piece['col']] = [
                'rank' => $piece['rank'],
                'color' => $color->value,
                'revealed' => false,
            ];
        }
        return $board;
    }

    /**
     * Validate a move
     */
    public function validateMove(Game $game, int $fromRow, int $fromCol, int $toRow, int $toCol, PlayerColor $playerColor): bool
    {
        $board = $game->board_state;

        // Check source has a piece
        if (!isset($board[$fromRow][$fromCol]) || $board[$fromRow][$fromCol] === null) {
            return false;
        }

        $piece = $board[$fromRow][$fromCol];

        // Check piece belongs to player
        if ($piece['color'] !== $playerColor->value) {
            return false;
        }

        $rank = PieceRank::from($piece['rank']);

        // Check piece can move (not flag or bomb)
        if (!$rank->canMove()) {
            return false;
        }

        // Check destination is not lake
        if ($this->isLake($toRow, $toCol)) {
            return false;
        }

        // Check destination is not own piece
        if (isset($board[$toRow][$toCol]) && $board[$toRow][$toCol] !== null) {
            if (($board[$toRow][$toCol]['type'] ?? null) !== 'lake') {
                if ($board[$toRow][$toCol]['color'] === $playerColor->value) {
                    return false;
                }
            }
        }

        // Validate movement pattern
        return $this->validateMovementPattern($rank, $fromRow, $fromCol, $toRow, $toCol, $board);
    }

    /**
     * Validate movement pattern based on piece rank
     */
    private function validateMovementPattern(PieceRank $rank, int $fromRow, int $fromCol, int $toRow, int $toCol, array $board): bool
    {
        $rowDiff = abs($toRow - $fromRow);
        $colDiff = abs($toCol - $fromCol);

        // Must move in straight line (not diagonal)
        if ($rowDiff > 0 && $colDiff > 0) {
            return false;
        }

        // Must move at least one square
        if ($rowDiff === 0 && $colDiff === 0) {
            return false;
        }

        // Scout can move multiple squares
        if ($rank === PieceRank::SCOUT) {
            // Check path is clear
            return $this->isPathClear($fromRow, $fromCol, $toRow, $toCol, $board);
        }

        // Other pieces can only move one square
        return $rowDiff + $colDiff === 1;
    }

    /**
     * Check if path is clear for scout movement
     */
    private function isPathClear(int $fromRow, int $fromCol, int $toRow, int $toCol, array $board): bool
    {
        $rowDir = $toRow <=> $fromRow;
        $colDir = $toCol <=> $fromCol;

        $currentRow = $fromRow + $rowDir;
        $currentCol = $fromCol + $colDir;

        while ($currentRow !== $toRow || $currentCol !== $toCol) {
            if ($this->isLake($currentRow, $currentCol)) {
                return false;
            }
            if (isset($board[$currentRow][$currentCol]) && $board[$currentRow][$currentCol] !== null) {
                if (($board[$currentRow][$currentCol]['type'] ?? null) !== 'lake') {
                    return false;
                }
            }
            $currentRow += $rowDir;
            $currentCol += $colDir;
        }

        return true;
    }

    /**
     * Execute a move and return the result
     */
    public function executeMove(Game $game, int $fromRow, int $fromCol, int $toRow, int $toCol, PlayerColor $playerColor): array
    {
        $board = $game->board_state;
        $attackerPiece = $board[$fromRow][$fromCol];
        $defenderPiece = $board[$toRow][$toCol] ?? null;

        $result = [
            'type' => 'move',
            'attacker' => $attackerPiece,
            'defender' => $defenderPiece,
            'captured' => null,
            'winner' => null,
        ];

        // If destination is empty, just move
        if ($defenderPiece === null || ($defenderPiece['type'] ?? null) === 'lake') {
            $board[$toRow][$toCol] = $attackerPiece;
            $board[$fromRow][$fromCol] = null;
        } else {
            // Combat!
            $attackerRank = PieceRank::from($attackerPiece['rank']);
            $defenderRank = PieceRank::from($defenderPiece['rank']);

            $combatResult = $attackerRank->winsAgainst($defenderRank);

            // Reveal both pieces
            $attackerPiece['revealed'] = true;
            $defenderPiece['revealed'] = true;

            if ($combatResult === true) {
                // Attacker wins
                $result['type'] = 'win';
                $result['captured'] = $defenderPiece;
                $board[$toRow][$toCol] = $attackerPiece;
                $board[$fromRow][$fromCol] = null;

                // Check if flag was captured
                if ($defenderRank === PieceRank::FLAG) {
                    $result['winner'] = $playerColor;
                }
            } elseif ($combatResult === false) {
                // Defender wins
                $result['type'] = 'lose';
                $result['captured'] = $attackerPiece;
                $board[$fromRow][$fromCol] = null;
                $board[$toRow][$toCol] = $defenderPiece;
            } else {
                // Draw - both destroyed
                $result['type'] = 'draw';
                $result['captured'] = [$attackerPiece, $defenderPiece];
                $board[$fromRow][$fromCol] = null;
                $board[$toRow][$toCol] = null;
            }
        }

        $game->board_state = $board;

        // Record the move
        $moveNumber = $game->moves()->count() + 1;
        Move::create([
            'game_id' => $game->id,
            'player_color' => $playerColor,
            'piece_rank' => $attackerPiece['rank'],
            'from_row' => $fromRow,
            'from_col' => $fromCol,
            'to_row' => $toRow,
            'to_col' => $toCol,
            'captured_rank' => $defenderPiece ? $defenderPiece['rank'] : null,
            'result' => $result['type'],
            'move_number' => $moveNumber,
        ]);

        // Check for winner
        if ($result['winner']) {
            $game->status = GameStatus::FINISHED;
            $game->winner = $result['winner'];
        } else {
            // Check if opponent has any movable pieces
            $opponentColor = $playerColor === PlayerColor::RED ? PlayerColor::BLUE : PlayerColor::RED;
            if (!$this->hasMovablePieces($board, $opponentColor)) {
                $result['winner'] = $playerColor;
                $game->status = GameStatus::FINISHED;
                $game->winner = $playerColor;
            }
        }

        if (!$result['winner']) {
            $game->switchTurn();
        }

        $game->save();

        return $result;
    }

    /**
     * Check if a player has any movable pieces
     */
    public function hasMovablePieces(array $board, PlayerColor $color): bool
    {
        for ($row = 0; $row < self::BOARD_SIZE; $row++) {
            for ($col = 0; $col < self::BOARD_SIZE; $col++) {
                $piece = $board[$row][$col] ?? null;
                if ($piece && ($piece['color'] ?? null) === $color->value) {
                    $rank = PieceRank::from($piece['rank']);
                    if ($rank->canMove() && $this->hasValidMove($board, $row, $col, $color)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Check if a piece has any valid move
     */
    private function hasValidMove(array $board, int $row, int $col, PlayerColor $color): bool
    {
        $directions = [[-1, 0], [1, 0], [0, -1], [0, 1]];

        foreach ($directions as $dir) {
            $newRow = $row + $dir[0];
            $newCol = $col + $dir[1];

            if ($newRow >= 0 && $newRow < self::BOARD_SIZE && $newCol >= 0 && $newCol < self::BOARD_SIZE) {
                if (!$this->isLake($newRow, $newCol)) {
                    $target = $board[$newRow][$newCol] ?? null;
                    if ($target === null || ($target['color'] ?? null) !== $color->value) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get board state for a specific player (hide opponent's unrevealed pieces)
     */
    public function getBoardForPlayer(array $board, PlayerColor $playerColor): array
    {
        $visibleBoard = [];

        for ($row = 0; $row < self::BOARD_SIZE; $row++) {
            $visibleBoard[$row] = [];
            for ($col = 0; $col < self::BOARD_SIZE; $col++) {
                $piece = $board[$row][$col] ?? null;

                if ($piece === null) {
                    $visibleBoard[$row][$col] = null;
                } elseif (($piece['type'] ?? null) === 'lake') {
                    $visibleBoard[$row][$col] = ['type' => 'lake'];
                } elseif ($piece['color'] === $playerColor->value || $piece['revealed']) {
                    $visibleBoard[$row][$col] = $piece;
                } else {
                    // Hide opponent's piece rank
                    $visibleBoard[$row][$col] = [
                        'color' => $piece['color'],
                        'hidden' => true,
                    ];
                }
            }
        }

        return $visibleBoard;
    }

    /**
     * Get all valid moves for a player
     */
    public function getValidMoves(array $board, PlayerColor $color): array
    {
        $moves = [];

        for ($row = 0; $row < self::BOARD_SIZE; $row++) {
            for ($col = 0; $col < self::BOARD_SIZE; $col++) {
                $piece = $board[$row][$col] ?? null;
                if ($piece && ($piece['color'] ?? null) === $color->value) {
                    $rank = PieceRank::from($piece['rank']);
                    if ($rank->canMove()) {
                        $pieceMoves = $this->getMovesForPiece($board, $row, $col, $rank, $color);
                        foreach ($pieceMoves as $move) {
                            $moves[] = [
                                'from' => ['row' => $row, 'col' => $col],
                                'to' => ['row' => $move[0], 'col' => $move[1]],
                                'rank' => $rank->value,
                            ];
                        }
                    }
                }
            }
        }

        return $moves;
    }

    /**
     * Get all valid moves for a specific piece
     */
    private function getMovesForPiece(array $board, int $row, int $col, PieceRank $rank, PlayerColor $color): array
    {
        $moves = [];
        $directions = [[-1, 0], [1, 0], [0, -1], [0, 1]];

        if ($rank === PieceRank::SCOUT) {
            // Scout can move multiple squares
            foreach ($directions as $dir) {
                $newRow = $row + $dir[0];
                $newCol = $col + $dir[1];

                while ($newRow >= 0 && $newRow < self::BOARD_SIZE && $newCol >= 0 && $newCol < self::BOARD_SIZE) {
                    if ($this->isLake($newRow, $newCol)) {
                        break;
                    }

                    $target = $board[$newRow][$newCol] ?? null;
                    if ($target === null) {
                        $moves[] = [$newRow, $newCol];
                    } elseif (($target['color'] ?? null) !== $color->value && ($target['type'] ?? null) !== 'lake') {
                        $moves[] = [$newRow, $newCol];
                        break; // Can't move past an enemy piece
                    } else {
                        break; // Own piece blocks
                    }

                    $newRow += $dir[0];
                    $newCol += $dir[1];
                }
            }
        } else {
            // Other pieces move one square
            foreach ($directions as $dir) {
                $newRow = $row + $dir[0];
                $newCol = $col + $dir[1];

                if ($newRow >= 0 && $newRow < self::BOARD_SIZE && $newCol >= 0 && $newCol < self::BOARD_SIZE) {
                    if (!$this->isLake($newRow, $newCol)) {
                        $target = $board[$newRow][$newCol] ?? null;
                        if ($target === null || (($target['color'] ?? null) !== $color->value && ($target['type'] ?? null) !== 'lake')) {
                            $moves[] = [$newRow, $newCol];
                        }
                    }
                }
            }
        }

        return $moves;
    }
}
