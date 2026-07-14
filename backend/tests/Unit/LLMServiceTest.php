<?php

namespace Tests\Unit;

use App\Enums\PieceRank;
use App\Enums\PlayerColor;
use App\Models\Game;
use App\Models\Move;
use App\Models\User;
use App\Services\GameService;
use App\Services\LLMService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LLMServiceTest extends TestCase
{
    use RefreshDatabase;

    private LLMService $llmService;
    private GameService $gameService;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.llm.provider' => 'openai_compatible']);
        $this->gameService = $this->createMock(GameService::class);
        $this->llmService = new LLMService($this->gameService);
    }

    public function test_generate_move_returns_valid_move_on_success(): void
    {
        $game = $this->createGameWithMoves();
        $validMoves = $this->getValidMoves();

        $this->gameService->method('getValidMoves')
            ->willReturn($validMoves);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'move' => 0,
                                'alternatives' => [1],
                                'why' => 'Attack enemy',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->llmService->generateMove($game);

        $this->assertNotNull($result);
        $this->assertEquals($validMoves[0], $result);
    }

    public function test_generate_move_returns_null_when_no_valid_moves(): void
    {
        $game = $this->createGameWithMoves();

        $this->gameService->method('getValidMoves')
            ->willReturn([]);

        $result = $this->llmService->generateMove($game);

        $this->assertNull($result);
    }

    public function test_generate_move_falls_back_to_random_move_on_invalid_response(): void
    {
        $game = $this->createGameWithMoves();
        $validMoves = $this->getValidMoves();

        $this->gameService->method('getValidMoves')
            ->willReturn($validMoves);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'invalid json',
                        ],
                    ],
                ],
            ], 200),
        ]);

        Log::shouldReceive('warning')->once();

        $result = $this->llmService->generateMove($game);

        $this->assertNotNull($result);
        $this->assertContains($result, $validMoves);
    }

    public function test_generate_move_falls_back_to_random_move_on_invalid_move_index(): void
    {
        $game = $this->createGameWithMoves();
        $validMoves = $this->getValidMoves();

        $this->gameService->method('getValidMoves')
            ->willReturn($validMoves);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'move' => 999, // Invalid index
                                'alternatives' => [998],
                                'why' => 'Invalid move',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        Log::shouldReceive('warning')->once();

        $result = $this->llmService->generateMove($game);

        $this->assertNotNull($result);
        $this->assertContains($result, $validMoves);
    }

    public function test_generate_move_falls_back_to_random_move_on_api_error(): void
    {
        $game = $this->createGameWithMoves();
        $validMoves = $this->getValidMoves();

        $this->gameService->method('getValidMoves')
            ->willReturn($validMoves);

        Http::fake([
            '*/chat/completions' => Http::response(null, 500),
        ]);

        Log::shouldReceive('error')->once();

        $result = $this->llmService->generateMove($game);

        $this->assertNotNull($result);
        $this->assertContains($result, $validMoves);
    }

    public function test_generate_move_falls_back_to_random_move_on_timeout(): void
    {
        $game = $this->createGameWithMoves();
        $validMoves = $this->getValidMoves();

        $this->gameService->method('getValidMoves')
            ->willReturn($validMoves);

        Http::fake(function () {
            throw new \Exception('Connection timeout');
        });

        Log::shouldReceive('error')->once();

        $result = $this->llmService->generateMove($game);

        $this->assertNotNull($result);
        $this->assertContains($result, $validMoves);
    }

    public function test_generate_move_handles_malformed_json_with_regex_fallback(): void
    {
        $game = $this->createGameWithMoves();
        $validMoves = $this->getValidMoves();

        $this->gameService->method('getValidMoves')
            ->willReturn($validMoves);

        // Simulate truncated/malformed JSON that still contains move data
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"move": 1, "why": "Advan',
                        ],
                    ],
                ],
            ], 200),
        ]);

        Log::shouldReceive('info')->once();

        $result = $this->llmService->generateMove($game);

        $this->assertNotNull($result);
        $this->assertEquals($validMoves[1], $result);
    }

    public function test_generate_move_selects_valid_move_from_multiple_options(): void
    {
        $game = $this->createGameWithMoves();
        $validMoves = [
            ['from' => ['row' => 1, 'col' => 0], 'to' => ['row' => 2, 'col' => 0]],
            ['from' => ['row' => 1, 'col' => 1], 'to' => ['row' => 2, 'col' => 1]],
            ['from' => ['row' => 1, 'col' => 2], 'to' => ['row' => 2, 'col' => 2]],
        ];

        $this->gameService->method('getValidMoves')
            ->willReturn($validMoves);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'move' => 2,
                                'alternatives' => [0, 1],
                                'why' => 'Best position',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        Log::shouldReceive('info')->once();

        $result = $this->llmService->generateMove($game);

        $this->assertEquals($validMoves[2], $result);
    }

    public function test_generate_move_handles_missing_content_in_response(): void
    {
        $game = $this->createGameWithMoves();
        $validMoves = $this->getValidMoves();

        $this->gameService->method('getValidMoves')
            ->willReturn($validMoves);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [],
                    ],
                ],
            ], 200),
        ]);

        Log::shouldReceive('warning')->once();

        $result = $this->llmService->generateMove($game);

        $this->assertNotNull($result);
        $this->assertContains($result, $validMoves);
    }

    public function test_generate_move_sends_correct_request_format(): void
    {
        $game = $this->createGameWithMoves();
        $validMoves = $this->getValidMoves();

        $this->gameService->method('getValidMoves')
            ->willReturn($validMoves);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'move' => 0,
                                'alternatives' => [1],
                                'why' => 'Good move',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->llmService->generateMove($game);

        Http::assertSent(function ($request) use ($validMoves) {
            $data = $request->data();
            
            return isset($data['model']) &&
                   isset($data['messages']) &&
                   count($data['messages']) === 2 &&
                   isset($data['messages'][0]['role']) &&
                   $data['messages'][0]['role'] === 'system' &&
                   isset($data['messages'][1]['role']) &&
                   $data['messages'][1]['role'] === 'user' &&
                   isset($data['response_format']) &&
                   isset($data['temperature']) &&
                   $data['temperature'] === 0.3 &&
                   isset($data['max_tokens']) &&
                   $data['max_tokens'] === 250;
        });
    }

    public function test_generate_move_includes_json_schema_in_request(): void
    {
        $game = $this->createGameWithMoves();
        $validMoves = $this->getValidMoves();

        $this->gameService->method('getValidMoves')
            ->willReturn($validMoves);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'move' => 0,
                                'alternatives' => [1],
                                'why' => 'Test',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->llmService->generateMove($game);

        Http::assertSent(function ($request) {
            $data = $request->data();
            
            return isset($data['response_format']['type']) &&
                   $data['response_format']['type'] === 'json_schema' &&
                   isset($data['response_format']['json_schema']['schema']) &&
                   isset($data['response_format']['json_schema']['schema']['properties']['move']) &&
                   isset($data['response_format']['json_schema']['schema']['properties']['alternatives']) &&
                   $data['response_format']['json_schema']['schema']['properties']['alternatives']['minItems'] === 2 &&
                   $data['response_format']['json_schema']['schema']['properties']['alternatives']['maxItems'] === 2 &&
                   isset($data['response_format']['json_schema']['schema']['properties']['why']) &&
                   in_array('alternatives', $data['response_format']['json_schema']['schema']['required'], true);
        });
    }

    public function test_generate_move_uses_configured_base_url(): void
    {
        config(['services.llm.base_url' => 'http://test-llm:1234/v1']);
        
        $llmService = new LLMService($this->gameService);
        $game = $this->createGameWithMoves();
        $validMoves = $this->getValidMoves();

        $this->gameService->method('getValidMoves')
            ->willReturn($validMoves);

        Http::fake([
            'http://test-llm:1234/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'move' => 0,
                                'alternatives' => [1],
                                'why' => 'Test',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $llmService->generateMove($game);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'http://test-llm:1234/v1/chat/completions');
        });
    }

    public function test_generate_move_uses_configured_model(): void
    {
        config(['services.llm.model' => 'test-model-name']);

        $llmService = new LLMService($this->gameService);
        $game = $this->createGameWithMoves();
        $validMoves = $this->getValidMoves();

        $this->gameService->method('getValidMoves')
            ->willReturn($validMoves);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'move' => 0,
                                'alternatives' => [1],
                                'why' => 'Test',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $llmService->generateMove($game);

        Http::assertSent(function ($request) {
            $data = $request->data();
            return $data['model'] === 'test-model-name';
        });
    }

    public function test_generate_move_uses_azure_configuration(): void
    {
        config([
            'services.llm.provider' => 'azure',
            'services.llm.api_key' => 'azure-test-key',
            'services.llm.azure_endpoint' => 'https://test-resource.openai.azure.com',
            'services.llm.azure_api_version' => '2024-10-21',
            'services.llm.azure_deployment' => 'gpt-5.4',
            'services.llm.model' => 'should-not-be-sent',
        ]);

        $llmService = new LLMService($this->gameService);
        $game = $this->createGameWithMoves();
        $validMoves = $this->getValidMoves();

        $this->gameService->method('getValidMoves')
            ->willReturn($validMoves);

        Http::fake([
            'https://test-resource.openai.azure.com/openai/deployments/gpt-5.4/chat/completions?api-version=2024-10-21' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'move' => 0,
                                'alternatives' => [1],
                                'why' => 'Test',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $llmService->generateMove($game);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->hasHeader('api-key', 'azure-test-key') &&
                   $request->url() === 'https://test-resource.openai.azure.com/openai/deployments/gpt-5.4/chat/completions?api-version=2024-10-21' &&
                   !isset($data['model']) &&
                   !isset($data['max_tokens']) &&
                   ($data['max_completion_tokens'] ?? null) === 250;
        });
    }

    public function test_generate_move_prompt_includes_strategic_memory_and_recent_history(): void
    {
        $game = $this->createPersistedGameWithMoves();
        $validMoves = $this->getValidMoves();

        $this->gameService->method('getValidMoves')
            ->willReturn($validMoves);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'move' => 0,
                                'alternatives' => [1],
                                'why' => 'Use memory',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->llmService->generateMove($game);

        Http::assertSent(function ($request) {
            $prompt = $request->data()['messages'][1]['content'];

            $this->assertStringContainsString('Strategic memory:', $prompt);
            $this->assertStringContainsString('captures B-lost:none R-lost:Minerx1', $prompt);
            $this->assertStringContainsString('revealed red:Captain@8,2', $prompt);
            $this->assertStringContainsString('unmoved red:', $prompt);
            $this->assertStringContainsString('objective front-row:', $prompt);
            $this->assertStringContainsString('Recent moves:', $prompt);
            $this->assertStringContainsString('R:Scout(8,0)->(7,0)', $prompt);
            $this->assertStringContainsString('B:Miner(1,1)->(2,1)', $prompt);

            return true;
        });
    }

    public function test_generate_move_uses_alternative_when_primary_repeats_last_blue_move(): void
    {
        $game = $this->createPersistedGameWithMoves();
        $validMoves = [
            ['from' => ['row' => 2, 'col' => 0], 'to' => ['row' => 1, 'col' => 0]],
            ['from' => ['row' => 1, 'col' => 1], 'to' => ['row' => 2, 'col' => 1]],
        ];

        $this->gameService->method('getValidMoves')
            ->willReturn($validMoves);

        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'move' => 0,
                                'alternatives' => [1],
                                'why' => 'Avoid cycle',
                            ]),
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->llmService->generateMove($game);

        $this->assertEquals($validMoves[1], $result);
    }

    private function createGameWithMoves(): Game
    {
        $game = new Game();
        $game->board_state = $this->createBoardWithPieces();
        $game->setRelation('moves', collect());

        return $game;
    }

    private function createPersistedGameWithMoves(): Game
    {
        $game = Game::create([
            'player_red_id' => User::factory()->create()->id,
            'player_blue_id' => null,
            'status' => 'in_progress',
            'current_turn' => 'blue',
            'board_state' => $this->createBoardWithPieces(),
            'is_vs_ai' => true,
            'ai_difficulty' => 'medium',
            'use_llm' => true,
            'red_setup_complete' => true,
            'blue_setup_complete' => true,
        ]);

        $moves = [
            ['player_color' => PlayerColor::RED, 'piece_rank' => PieceRank::SCOUT, 'from_row' => 8, 'from_col' => 0, 'to_row' => 7, 'to_col' => 0, 'captured_rank' => null, 'result' => 'move'],
            ['player_color' => PlayerColor::BLUE, 'piece_rank' => PieceRank::MINER, 'from_row' => 1, 'from_col' => 1, 'to_row' => 2, 'to_col' => 1, 'captured_rank' => null, 'result' => 'move'],
            ['player_color' => PlayerColor::RED, 'piece_rank' => PieceRank::MINER, 'from_row' => 8, 'from_col' => 1, 'to_row' => 7, 'to_col' => 1, 'captured_rank' => PieceRank::SCOUT, 'result' => 'lose'],
            ['player_color' => PlayerColor::BLUE, 'piece_rank' => PieceRank::SCOUT, 'from_row' => 1, 'from_col' => 0, 'to_row' => 2, 'to_col' => 0, 'captured_rank' => null, 'result' => 'move'],
        ];

        foreach ($moves as $index => $move) {
            Move::create(array_merge($move, [
                'game_id' => $game->id,
                'move_number' => $index + 1,
            ]));
        }

        return $game->fresh();
    }

    private function createBoardWithPieces(): array
    {
        $board = array_fill(0, 10, array_fill(0, 10, null));
        
        // Add some blue pieces
        $board[1][0] = ['color' => 'blue', 'rank' => 2, 'revealed' => false];
        $board[1][1] = ['color' => 'blue', 'rank' => 3, 'revealed' => false];
        $board[1][2] = ['color' => 'blue', 'rank' => 4, 'revealed' => false];
        
        // Add some red pieces
        $board[8][0] = ['color' => 'red', 'rank' => 2, 'revealed' => false];
        $board[8][1] = ['color' => 'red', 'rank' => 3, 'revealed' => false];
        $board[8][2] = ['color' => 'red', 'rank' => 6, 'revealed' => true];
        
        // Add lakes
        $board[4][2] = ['type' => 'lake'];
        $board[4][3] = ['type' => 'lake'];
        $board[5][2] = ['type' => 'lake'];
        $board[5][3] = ['type' => 'lake'];
        $board[4][6] = ['type' => 'lake'];
        $board[4][7] = ['type' => 'lake'];
        $board[5][6] = ['type' => 'lake'];
        $board[5][7] = ['type' => 'lake'];
        
        return $board;
    }

    private function getValidMoves(): array
    {
        return [
            ['from' => ['row' => 1, 'col' => 0], 'to' => ['row' => 2, 'col' => 0]],
            ['from' => ['row' => 1, 'col' => 1], 'to' => ['row' => 2, 'col' => 1]],
        ];
    }
}
