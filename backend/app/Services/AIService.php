<?php

namespace App\Services;

use App\Enums\PieceRank;
use App\Enums\PlayerColor;
use App\Models\Game;

class AIService
{
    private GameService $gameService;

    public function __construct(GameService $gameService)
    {
        $this->gameService = $gameService;
    }

    /**
     * Generate AI's initial piece setup
     */
    public function generateSetup(PlayerColor $color): array
    {
        $pieces = [];
        $availablePieces = $this->getAvailablePieces();

        // Blue places on rows 0-3
        $rows = $color === PlayerColor::BLUE ? [0, 1, 2, 3] : [6, 7, 8, 9];

        // Create list of all positions
        $positions = [];
        foreach ($rows as $row) {
            for ($col = 0; $col < GameService::BOARD_SIZE; $col++) {
                if (!$this->gameService->isLake($row, $col)) {
                    $positions[] = ['row' => $row, 'col' => $col];
                }
            }
        }

        // Shuffle positions for randomness
        shuffle($positions);

        // Strategic placement
        $backRow = $color === PlayerColor::BLUE ? 0 : 9;
        $frontRow = $color === PlayerColor::BLUE ? 3 : 6;

        // Place flag in back row (protected position)
        $flagCol = rand(2, 7); // Middle-ish columns
        $pieces[] = ['row' => $backRow, 'col' => $flagCol, 'rank' => PieceRank::FLAG->value];
        $availablePieces[PieceRank::FLAG->value]--;
        $this->removePosition($positions, $backRow, $flagCol);

        // Place bombs around flag
        $bombPositions = [
            ['row' => $backRow, 'col' => $flagCol - 1],
            ['row' => $backRow, 'col' => $flagCol + 1],
            ['row' => $backRow + ($color === PlayerColor::BLUE ? 1 : -1), 'col' => $flagCol],
        ];

        foreach ($bombPositions as $pos) {
            if ($availablePieces[PieceRank::BOMB->value] > 0 &&
                $pos['row'] >= min($rows) && $pos['row'] <= max($rows) &&
                $pos['col'] >= 0 && $pos['col'] < GameService::BOARD_SIZE) {
                $pieces[] = ['row' => $pos['row'], 'col' => $pos['col'], 'rank' => PieceRank::BOMB->value];
                $availablePieces[PieceRank::BOMB->value]--;
                $this->removePosition($positions, $pos['row'], $pos['col']);
            }
        }

        // Place remaining bombs in back rows
        for ($i = 0; $i < $availablePieces[PieceRank::BOMB->value]; $i++) {
            $backPositions = array_filter($positions, fn($p) => $p['row'] === $backRow || $p['row'] === ($color === PlayerColor::BLUE ? 1 : 8));
            if (!empty($backPositions)) {
                $pos = $backPositions[array_rand($backPositions)];
                $pieces[] = ['row' => $pos['row'], 'col' => $pos['col'], 'rank' => PieceRank::BOMB->value];
                $this->removePosition($positions, $pos['row'], $pos['col']);
            }
        }
        $availablePieces[PieceRank::BOMB->value] = 0;

        // Place high-value pieces in second row (Marshal, General, Colonel)
        $highValueRanks = [PieceRank::MARSHAL, PieceRank::GENERAL, PieceRank::COLONEL, PieceRank::MAJOR];
        foreach ($highValueRanks as $rank) {
            while ($availablePieces[$rank->value] > 0) {
                $secondRowPositions = array_filter($positions, fn($p) => $p['row'] === ($color === PlayerColor::BLUE ? 1 : 8));
                if (empty($secondRowPositions)) {
                    $secondRowPositions = $positions;
                }
                $pos = $secondRowPositions[array_rand($secondRowPositions)];
                $pieces[] = ['row' => $pos['row'], 'col' => $pos['col'], 'rank' => $rank->value];
                $availablePieces[$rank->value]--;
                $this->removePosition($positions, $pos['row'], $pos['col']);
            }
        }

        // Place miners somewhat toward the front (to defuse bombs)
        while ($availablePieces[PieceRank::MINER->value] > 0) {
            $minerPositions = array_filter($positions, fn($p) =>
                $p['row'] === $frontRow || $p['row'] === ($color === PlayerColor::BLUE ? 2 : 7)
            );
            if (empty($minerPositions)) {
                $minerPositions = $positions;
            }
            $pos = $minerPositions[array_rand($minerPositions)];
            $pieces[] = ['row' => $pos['row'], 'col' => $pos['col'], 'rank' => PieceRank::MINER->value];
            $availablePieces[PieceRank::MINER->value]--;
            $this->removePosition($positions, $pos['row'], $pos['col']);
        }

        // Place scouts at front (mobile reconnaissance)
        while ($availablePieces[PieceRank::SCOUT->value] > 0) {
            $scoutPositions = array_filter($positions, fn($p) => $p['row'] === $frontRow);
            if (empty($scoutPositions)) {
                $scoutPositions = $positions;
            }
            $pos = $scoutPositions[array_rand($scoutPositions)];
            $pieces[] = ['row' => $pos['row'], 'col' => $pos['col'], 'rank' => PieceRank::SCOUT->value];
            $availablePieces[PieceRank::SCOUT->value]--;
            $this->removePosition($positions, $pos['row'], $pos['col']);
        }

        // Place spy near front (to take out Marshal)
        if ($availablePieces[PieceRank::SPY->value] > 0) {
            $spyPositions = array_filter($positions, fn($p) => $p['row'] === $frontRow || $p['row'] === ($color === PlayerColor::BLUE ? 2 : 7));
            if (empty($spyPositions)) {
                $spyPositions = $positions;
            }
            $pos = $spyPositions[array_rand($spyPositions)];
            $pieces[] = ['row' => $pos['row'], 'col' => $pos['col'], 'rank' => PieceRank::SPY->value];
            $availablePieces[PieceRank::SPY->value]--;
            $this->removePosition($positions, $pos['row'], $pos['col']);
        }

        // Place remaining pieces randomly
        foreach ($availablePieces as $rank => $count) {
            for ($i = 0; $i < $count; $i++) {
                if (!empty($positions)) {
                    $pos = $positions[array_rand($positions)];
                    $pieces[] = ['row' => $pos['row'], 'col' => $pos['col'], 'rank' => $rank];
                    $this->removePosition($positions, $pos['row'], $pos['col']);
                }
            }
        }

        return $pieces;
    }

    /**
     * Make AI move
     */
    public function makeMove(Game $game): ?array
    {
        $board = $game->board_state;
        $aiColor = PlayerColor::BLUE; // AI is always blue

        $validMoves = $this->gameService->getValidMoves($board, $aiColor);

        if (empty($validMoves)) {
            return null;
        }

        // Score each move
        $scoredMoves = [];
        foreach ($validMoves as $move) {
            $score = $this->scoreMove($board, $move, $aiColor, $game->ai_difficulty);
            $scoredMoves[] = ['move' => $move, 'score' => $score];
        }

        // Sort by score
        usort($scoredMoves, fn($a, $b) => $b['score'] <=> $a['score']);

        // Select move based on difficulty
        $selectedMove = match($game->ai_difficulty) {
            'easy' => $this->selectEasyMove($scoredMoves),
            'hard' => $scoredMoves[0]['move'], // Always best move
            default => $this->selectMediumMove($scoredMoves),
        };

        return $selectedMove;
    }

    /**
     * Score a potential move
     */
    private function scoreMove(array $board, array $move, PlayerColor $aiColor, string $difficulty): float
    {
        $score = 0.0;
        $from = $move['from'];
        $to = $move['to'];

        $attackerPiece = $board[$from['row']][$from['col']];
        $defenderPiece = $board[$to['row']][$to['col']] ?? null;
        $attackerRank = PieceRank::from($attackerPiece['rank']);

        // Attacking an enemy piece
        if ($defenderPiece && ($defenderPiece['color'] ?? null) !== $aiColor->value) {
            if ($defenderPiece['revealed'] ?? false) {
                // We know what the piece is
                $defenderRank = PieceRank::from($defenderPiece['rank']);
                $combatResult = $attackerRank->winsAgainst($defenderRank);

                if ($combatResult === true) {
                    // Winning attack
                    $score += 100 + $this->getPieceValue($defenderRank);

                    // Huge bonus for capturing flag
                    if ($defenderRank === PieceRank::FLAG) {
                        $score += 10000;
                    }
                } elseif ($combatResult === false) {
                    // Losing attack
                    $score -= 50 + $this->getPieceValue($attackerRank);
                } else {
                    // Draw
                    $score += $this->getPieceValue($defenderRank) - $this->getPieceValue($attackerRank);
                }
            } else {
                // Unknown piece - risk assessment
                $score += $this->assessUnknownAttack($attackerRank, $from, $to, $difficulty);
            }
        }

        // Positional scoring
        $score += $this->scorePosition($from, $to, $attackerRank, $aiColor);

        // Scout mobility bonus
        if ($attackerRank === PieceRank::SCOUT) {
            $distance = abs($to['row'] - $from['row']) + abs($to['col'] - $from['col']);
            $score += $distance * 2; // Scouts get bonus for long moves
        }

        // Add randomness
        $score += mt_rand(0, 10) / 10;

        return $score;
    }

    /**
     * Assess risk of attacking an unknown piece
     */
    private function assessUnknownAttack(PieceRank $attackerRank, array $from, array $to, string $difficulty): float
    {
        $attackerValue = $this->getPieceValue($attackerRank);

        // Higher ranked pieces are more valuable, so be more cautious
        if ($attackerRank === PieceRank::MARSHAL) {
            return -20; // Marshal should be careful - might be bomb or spy
        }

        if ($attackerRank === PieceRank::MINER) {
            return 30; // Miners are good for attacking unknowns (might be bomb)
        }

        if ($attackerRank === PieceRank::SCOUT) {
            return 15; // Scouts are expendable scouts
        }

        // Medium and low rank pieces
        return 10 - $attackerValue / 5;
    }

    /**
     * Score position advancement
     */
    private function scorePosition(array $from, array $to, PieceRank $rank, PlayerColor $color): float
    {
        $score = 0.0;

        // For Blue AI, advancing means going to higher row numbers
        if ($to['row'] > $from['row']) {
            $score += 5; // Advance bonus
        }

        // Center control
        $centerBonus = 10 - abs($to['col'] - 4.5) * 2;
        $score += $centerBonus;

        // Don't move high value pieces too aggressively
        if ($rank === PieceRank::MARSHAL || $rank === PieceRank::GENERAL) {
            if ($to['row'] > 6) { // Too far into enemy territory
                $score -= 20;
            }
        }

        return $score;
    }

    /**
     * Get piece value for scoring
     */
    private function getPieceValue(PieceRank $rank): int
    {
        return match($rank) {
            PieceRank::FLAG => 1000,
            PieceRank::SPY => 25,
            PieceRank::SCOUT => 10,
            PieceRank::MINER => 30,
            PieceRank::SERGEANT => 15,
            PieceRank::LIEUTENANT => 20,
            PieceRank::CAPTAIN => 25,
            PieceRank::MAJOR => 35,
            PieceRank::COLONEL => 45,
            PieceRank::GENERAL => 60,
            PieceRank::MARSHAL => 80,
            PieceRank::BOMB => 40,
        };
    }

    /**
     * Select move for easy difficulty (more random)
     */
    private function selectEasyMove(array $scoredMoves): array
    {
        // Take from top 50% with bias toward random
        $topHalf = array_slice($scoredMoves, 0, max(1, (int)(count($scoredMoves) * 0.5)));
        return $topHalf[array_rand($topHalf)]['move'];
    }

    /**
     * Select move for medium difficulty
     */
    private function selectMediumMove(array $scoredMoves): array
    {
        // Take from top 25% with some randomness
        $topQuarter = array_slice($scoredMoves, 0, max(1, (int)(count($scoredMoves) * 0.25)));
        return $topQuarter[array_rand($topQuarter)]['move'];
    }

    /**
     * Get initial piece counts
     */
    private function getAvailablePieces(): array
    {
        $pieces = [];
        foreach (PieceRank::cases() as $rank) {
            $pieces[$rank->value] = $rank->getCount();
        }
        return $pieces;
    }

    /**
     * Remove position from available positions
     */
    private function removePosition(array &$positions, int $row, int $col): void
    {
        $positions = array_values(array_filter($positions, fn($p) => !($p['row'] === $row && $p['col'] === $col)));
    }
}
