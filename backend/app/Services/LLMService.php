<?php

namespace App\Services;

use App\Enums\PieceRank;
use App\Enums\PlayerColor;
use App\Models\Game;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LLMService
{
    private string $baseUrl;
    private GameService $gameService;

    public function __construct(GameService $gameService)
    {
        $this->baseUrl = config('services.llm.base_url', 'http://localhost:1234/v1');
        $this->gameService = $gameService;
    }

    /**
     * Generate a move using the LLM model
     */
    public function generateMove(Game $game): ?array
    {
        $board = $game->board_state;
        $aiColor = PlayerColor::BLUE;

        $validMoves = $this->gameService->getValidMoves($board, $aiColor);

        if (empty($validMoves)) {
            return null;
        }

        // Re-index to ensure sequential numbering from 0
        $validMoves = array_values($validMoves);
        $prompt = $this->buildMovePrompt($game, $validMoves);

        try {
            $response = Http::timeout(30)->post("{$this->baseUrl}/chat/completions", [
                'model' => config('services.llm.model', 'local-model'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getSystemPrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'stratego_move',
                        'strict' => true,
                        'schema' => $this->getMoveSchema(count($validMoves) - 1),
                    ],
                ],
                'temperature' => 0.7,
                'max_tokens' => 100,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                $content = $data['choices'][0]['message']['content'] ?? null;

                if ($content) {
                    $result = json_decode($content, true);
                    
                    // Try to get move index from response
                    $moveIndex = $result['move'] ?? null;
                    
                    // Fallback: try to extract number from truncated/malformed response
                    if ($moveIndex === null && preg_match('/"move"\s*:\s*(\d+)/', $content, $matches)) {
                        $moveIndex = (int) $matches[1];
                    }

                    if ($moveIndex !== null && isset($validMoves[$moveIndex])) {
                        Log::info('LLM move generated', [
                            'move_index' => $moveIndex,
                            'move' => $validMoves[$moveIndex],
                            'why' => $result['why'] ?? '',
                        ]);
                        return $validMoves[$moveIndex];
                    } else {
                        Log::warning('LLM returned invalid move index', [
                            'move_index' => $moveIndex,
                            'max_index' => count($validMoves) - 1,
                            'raw_content' => substr($content, 0, 200),
                        ]);
                    }
                }
            }

            Log::warning('LLM response invalid or failed, falling back to random valid move', [
                'response' => $response->json(),
            ]);
        } catch (\Exception $e) {
            Log::error('LLM API error', ['error' => $e->getMessage()]);
        }

        // Fallback: return a random valid move
        return $validMoves[array_rand($validMoves)];
    }

    /**
     * Get the system prompt for the LLM
     */
    private function getSystemPrompt(): string
    {
        return <<<PROMPT
You are an AGGRESSIVE Stratego AI (Blue). Pick the best move NUMBER from the list.

Rules: Higher rank beats lower (10=Marshal>1=Spy). Spy beats Marshal when attacking. Miner(3) defuses Bombs. Bombs(11) kill attackers except Miners. Equal ranks: both die.

PRIORITY ORDER:
1. ATK moves that WIN (your rank > enemy rank)
2. ATK:? with Scouts to probe unknowns
3. Advance toward enemy (higher row numbers)
4. NEVER move back and forth - always progress!

Be aggressive! Attack when you can win.
PROMPT;
    }

    /**
     * Build the move prompt with current game state
     */
    private function buildMovePrompt(Game $game, array $validMoves): string
    {
        $board = $game->board_state;
        $boardStr = $this->formatBoardForLLM($board);
        $movesStr = $this->formatMovesForLLM($validMoves, $board);

        return "Board:{$boardStr}\nMoves:\n{$movesStr}\nPick move# (0-" . (count($validMoves) - 1) . "). Prefer ATK moves!";
    }

    /**
     * Format the board for LLM consumption as compact JSON
     */
    private function formatBoardForLLM(array $board): string
    {
        $pieces = [];

        for ($row = 0; $row < 10; $row++) {
            for ($col = 0; $col < 10; $col++) {
                if (isset($board[$row][$col]) && $board[$row][$col] !== null && !$this->gameService->isLake($row, $col)) {
                    $piece = $board[$row][$col];
                    
                    if ($piece['color'] === 'blue') {
                        // Blue pieces: show rank
                        $pieces[] = ['r' => $row, 'c' => $col, 'B' => $piece['rank']];
                    } else {
                        // Enemy pieces: show rank if revealed, otherwise "?"
                        if ($piece['revealed'] ?? false) {
                            $pieces[] = ['r' => $row, 'c' => $col, 'E' => $piece['rank']];
                        } else {
                            $pieces[] = ['r' => $row, 'c' => $col, 'E' => '?'];
                        }
                    }
                }
            }
        }

        return json_encode($pieces);
    }

    /**
     * Format valid moves for LLM as numbered list, with attacks first
     */
    private function formatMovesForLLM(array $validMoves, array $board): string
    {
        $attackMoves = [];
        $normalMoves = [];

        foreach ($validMoves as $index => $move) {
            $from = $move['from'];
            $to = $move['to'];
            $piece = $board[$from['row']][$from['col']];
            $rankName = PieceRank::from($piece['rank'])->getName();

            $moveStr = "{$index}:{$rankName}({$from['row']},{$from['col']})->({$to['row']},{$to['col']})";

            // Check if this is an attack
            if (isset($board[$to['row']][$to['col']]) && $board[$to['row']][$to['col']] !== null) {
                $target = $board[$to['row']][$to['col']];
                if ($target['revealed'] ?? false) {
                    $targetRank = $target['rank'];
                    $targetName = PieceRank::from($targetRank)->getName();
                    $myRank = $piece['rank'];
                    // Mark winning attacks prominently
                    if ($myRank > $targetRank || ($myRank === 1 && $targetRank === 10) || ($myRank === 3 && $targetRank === 11)) {
                        $moveStr .= " ATK:{$targetName}[WIN!]";
                    } else {
                        $moveStr .= " ATK:{$targetName}";
                    }
                } else {
                    $moveStr .= " ATK:?";
                }
                $attackMoves[] = $moveStr;
            } else {
                $normalMoves[] = $moveStr;
            }
        }

        // Put attack moves first, then normal moves
        $lines = array_merge($attackMoves, $normalMoves);

        return implode("\n", $lines);
    }

    /**
     * Get the JSON schema for move response
     */
    private function getMoveSchema(int $maxMove): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'move' => [
                    'type' => 'integer',
                    'description' => "Move number 0 to {$maxMove}",
                ],
                'why' => [
                    'type' => 'string',
                    'description' => '1-5 words only',
                ],
            ],
            'required' => ['move', 'why'],
            'additionalProperties' => false,
        ];
    }
}
