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
    private string $provider;
    private ?string $apiKey;
    private ?string $azureEndpoint;
    private string $azureApiVersion;
    private ?string $azureDeployment;
    private GameService $gameService;

    public function __construct(GameService $gameService)
    {
        $this->baseUrl = rtrim(config('services.llm.base_url', 'http://localhost:1234/v1'), '/');
        $this->provider = config('services.llm.provider', 'openai_compatible');
        $this->apiKey = config('services.llm.api_key');
        $this->azureEndpoint = config('services.llm.azure_endpoint');
        $this->azureApiVersion = config('services.llm.azure_api_version', '2024-10-21');
        $this->azureDeployment = config('services.llm.azure_deployment');
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
            $response = $this->createRequest()->post($this->getChatCompletionsUrl(), $this->buildRequestPayload($prompt, count($validMoves) - 1));

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
                        // Fallback to random valid move
                        return $validMoves[array_rand($validMoves)];
                    }
                }
            }

            Log::warning('LLM response invalid or failed, falling back to random valid move', [
                'response' => $response->json(),
            ]);
        } catch (\Exception $e) {
            // Log error and fall back to random move for API/connection errors
            Log::error('LLM API error, falling back to random move', [
                'error' => $e->getMessage(),
                'exception_type' => get_class($e),
            ]);
        }

        // Final fallback: return a random valid move when no valid move was parsed from a successful LLM response
        return $validMoves[array_rand($validMoves)];
    }

    private function createRequest()
    {
        $request = Http::timeout(30);

        if ($this->provider === 'azure') {
            if ($this->apiKey) {
                $request = $request->withHeaders(['api-key' => $this->apiKey]);
            }

            return $request;
        }

        if ($this->apiKey) {
            $request = $request->withToken($this->apiKey);
        }

        return $request;
    }

    private function getChatCompletionsUrl(): string
    {
        if ($this->provider === 'azure') {
            $endpoint = rtrim($this->azureEndpoint ?: '', '/');
            $deployment = $this->azureDeployment ?: config('services.llm.model', 'local-model');

            return "{$endpoint}/openai/deployments/{$deployment}/chat/completions?api-version={$this->azureApiVersion}";
        }

        return "{$this->baseUrl}/chat/completions";
    }

    private function buildRequestPayload(string $prompt, int $maxMove): array
    {
        $payload = [
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
                    'schema' => $this->getMoveSchema($maxMove),
                ],
            ],
            'temperature' => 0.3,
            'max_tokens' => 150,
        ];

        if ($this->provider !== 'azure') {
            $payload['model'] = config('services.llm.model', 'local-model');
        }

        return $payload;
    }

    /**
     * Get the system prompt for the LLM
     */
    private function getSystemPrompt(): string
    {
        return <<<PROMPT
You are an AGGRESSIVE Stratego AI playing as Blue (you start at rows 0-3). Your MAIN GOAL: CAPTURE THE ENEMY FLAG!

RANKS (higher beats lower):
- Marshal(10) > General(9) > Colonel(8) > Major(7) > Captain(6) > Lieutenant(5) > Sergeant(4) > Miner(3) > Scout(2) > Spy(1)
- Flag(0) and Bomb(11) cannot move
- Spy(1) beats Marshal(10) ONLY when attacking
- Miner(3) defuses Bombs(11); all others die to Bombs
- Equal ranks: both pieces die

FLAG HUNTING STRATEGY:
- Enemy (Red) Flag is in rows 6-9, usually row 8-9 (Red's back row)
- Flags are often in corners or surrounded by Bombs
- Pieces that NEVER move are likely Bombs or the Flag!
- Use Miners(3) to clear Bombs protecting the Flag
- Rush Scouts/expendable pieces to probe the back row

OFFENSIVE PRIORITIES:
1. ATTACK: If you can WIN, ATTACK! Eliminate enemy pieces aggressively
2. FLAG HUNT: Push pieces toward rows 8-9 to find the Flag
3. PROBE: Attack unknowns with Scouts/low-value pieces to reveal them
4. INFILTRATE: Get Miners into enemy territory to defuse Bomb defenses
5. OVERWHELM: Trade pieces if it opens a path to the back row

BE AGGRESSIVE:
- Always prefer attacking over retreating
- Sacrifice low-value pieces to reveal high-value targets
- Push forward relentlessly - the Flag won't come to you!
- Prioritize moves to rows 6-9, especially row 8-9 (FLAG ZONE)

AVOID:
- Moving the same piece back and forth (NEVER retreat repeatedly)
- Being passive - attack when possible!
- Attacking unknowns with Marshal(10) - might be a Spy trap
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
        $historyStr = $this->getRecentMoveHistory($game);

        $prompt = "Board:{$boardStr}\n";
        if ($historyStr) {
            $prompt .= "Recent moves:{$historyStr}\n";
        }
        $prompt .= "Valid moves (best first):\n{$movesStr}\n";
        $prompt .= "Pick the BEST move# (0-" . (count($validMoves) - 1) . "):";

        return $prompt;
    }

    /**
     * Get recent move history to prevent repetition
     */
    private function getRecentMoveHistory(Game $game): string
    {
        $moves = $game->moves()->latest()->take(4)->get()->reverse();
        if ($moves->isEmpty()) {
            return '';
        }

        $history = [];
        foreach ($moves as $move) {
            $color = $move->player_color->value === 'blue' ? 'B' : 'R';
            $rankName = $move->piece_rank->getName();
            $moveStr = "{$color}:{$rankName}({$move->from_row},{$move->from_col})->({$move->to_row},{$move->to_col})";
            
            // Add capture info if applicable
            if ($move->captured_rank !== null) {
                $capturedName = $move->captured_rank->getName();
                $moveStr .= "x{$capturedName}";
                if ($move->result) {
                    $moveStr .= "=" . strtoupper($move->result);
                }
            }
            
            $history[] = $moveStr;
        }
        return implode(', ', $history);
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
                    
                    // Skip lake entries or pieces without color
                    if (!isset($piece['color'])) {
                        continue;
                    }
                    
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
     * Format valid moves for LLM as numbered list, scored and sorted by quality
     */
    private function formatMovesForLLM(array $validMoves, array $board): string
    {
        $scoredMoves = [];

        foreach ($validMoves as $index => $move) {
            $from = $move['from'];
            $to = $move['to'];
            $piece = $board[$from['row']][$from['col']];
            $rankName = PieceRank::from($piece['rank'])->getName();
            $myRank = $piece['rank'];

            $moveStr = "{$index}:{$rankName}({$from['row']},{$from['col']})->({$to['row']},{$to['col']})";
            $score = 0;
            $tags = [];

            // Check if this is an attack
            $target = $board[$to['row']][$to['col']] ?? null;
            if ($target !== null && isset($target['color'])) {
                $targetRevealed = $target['revealed'] ?? false;
                
                if ($targetRevealed) {
                    $targetRank = $target['rank'];
                    $targetName = PieceRank::from($targetRank)->getName();
                    
                    // Flag capture = instant win!
                    if ($targetRank === 0) {
                        $score = 1000;
                        $tags[] = "ATK:FLAG=VICTORY!";
                    } elseif ($this->willWinAttack($myRank, $targetRank)) {
                        $score = 150 + ($targetRank * 5); // Higher value targets = higher score
                        $tags[] = "ATK:{$targetName}=WIN";
                    } elseif ($myRank === $targetRank) {
                        $score = 30; // Trades are acceptable offensively
                        $tags[] = "ATK:{$targetName}=TIE";
                    } else {
                        $score = -50;
                        $tags[] = "ATK:{$targetName}=LOSE";
                    }
                } else {
                    // Unknown target - be aggressive with expendable pieces!
                    if ($myRank === 2) {
                        $score = 60; // Scouts are meant to probe
                        $tags[] = "ATK:?";
                        $tags[] = "PROBE";
                    } elseif ($myRank <= 4) {
                        $score = 40; // Low value pieces can probe
                        $tags[] = "ATK:?";
                    } elseif ($myRank === 10) {
                        $score = -20; // Marshal at risk of Spy
                        $tags[] = "ATK:?";
                        $tags[] = "RISKY(spy-trap)";
                    } else {
                        $score = 15; // Mid-value pieces: mild risk
                        $tags[] = "ATK:?";
                    }
                    
                    // Bonus for attacking in flag zone (rows 8-9)
                    if ($to['row'] >= 8) {
                        $score += 25;
                        $tags[] = "FLAG-ZONE";
                    }
                }
            } else {
                // Movement - strongly prefer advancing toward flag (higher rows for Blue)
                $advancement = $to['row'] - $from['row']; // Positive = toward enemy (higher rows)
                if ($advancement > 0) {
                    $score = 10 + ($advancement * 5);
                    $tags[] = "ADVANCE";
                    
                    // Big bonus for reaching flag zone
                    if ($to['row'] >= 8) {
                        $score += 30;
                        $tags[] = "FLAG-ZONE";
                    } elseif ($to['row'] >= 6) {
                        $score += 15;
                        $tags[] = "ENEMY-TERRITORY";
                    }
                    
                    // Extra bonus for Miners advancing (they can defuse bombs)
                    if ($myRank === 3 && $to['row'] >= 6) {
                        $score += 20;
                        $tags[] = "MINER-INFILTRATE";
                    }
                } else {
                    $score = -5; // Penalize retreating
                    if ($advancement < 0) $tags[] = "RETREAT";
                }
            }

            $tagStr = empty($tags) ? '' : ' [' . implode(',', $tags) . ']';
            $scoredMoves[] = [
                'score' => $score,
                'str' => "{$moveStr}{$tagStr}",
            ];
        }

        // Sort by score descending so best moves appear first
        usort($scoredMoves, fn($a, $b) => $b['score'] <=> $a['score']);

        return implode("\n", array_column($scoredMoves, 'str'));
    }

    /**
     * Determine if attacker wins against defender
     */
    private function willWinAttack(int $attackerRank, int $defenderRank): bool
    {
        // Spy beats Marshal when attacking
        if ($attackerRank === 1 && $defenderRank === 10) {
            return true;
        }
        // Miner defuses Bomb
        if ($attackerRank === 3 && $defenderRank === 11) {
            return true;
        }
        // Bomb kills attacker (unless Miner)
        if ($defenderRank === 11) {
            return false;
        }
        // Higher rank wins
        return $attackerRank > $defenderRank;
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
