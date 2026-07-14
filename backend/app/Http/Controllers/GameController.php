<?php

namespace App\Http\Controllers;

use App\Enums\GameStatus;
use App\Enums\PlayerColor;
use App\Events\GameCreated;
use App\Events\GameStarted;
use App\Events\GameUpdated;
use App\Events\MoveMade;
use App\Events\SetupComplete;
use App\Models\Game;
use App\Services\AIService;
use App\Services\GameService;
use App\Services\LLMService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GameController extends Controller
{
    public function __construct(
        private readonly GameService $gameService,
        private readonly AIService $aiService,
        private readonly LLMService $llmService
    ) {
        // Inject LLM service into AI service
        $this->aiService->setLLMService($this->llmService);
    }

    /**
     * List all games for the current user
     */
    public function index(): JsonResponse
    {
        $userId = Auth::id();

        $games = Game::where('player_red_id', $userId)
            ->orWhere('player_blue_id', $userId)
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json($games);
    }

    /**
     * List open games waiting for players
     */
    public function openGames(): JsonResponse
    {
        $games = Game::where('status', GameStatus::WAITING)
            ->where('is_vs_ai', false)
            ->whereNull('player_blue_id')
            ->with('playerRed:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($games);
    }

    /**
     * Create a new game
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'vs_ai' => 'boolean',
            'ai_difficulty' => 'string|in:easy,medium,hard',
            'use_llm' => 'boolean',
        ]);

        // Validate that use_llm can only be true when vs_ai is true
        if (($validated['use_llm'] ?? false) && !($validated['vs_ai'] ?? false)) {
            return response()->json([
                'message' => 'The use_llm parameter can only be true when vs_ai is true.',
                'errors' => [
                    'use_llm' => ['The use_llm field requires vs_ai to be true.']
                ]
            ], 422);
        }

        $game = new Game();
        $game->player_red_id = Auth::id();
        $game->status = GameStatus::WAITING;
        $game->current_turn = PlayerColor::RED;
        $game->board_state = $this->gameService->createEmptyBoard();
        $game->is_vs_ai = $validated['vs_ai'] ?? false;
        $game->ai_difficulty = $validated['ai_difficulty'] ?? 'medium';
        $game->use_llm = $validated['use_llm'] ?? false;

        if ($game->is_vs_ai) {
            $game->status = GameStatus::SETUP;
        }

        $game->save();

        Log::info('Game created', [
            'game_id' => $game->id,
            'player_red_id' => $game->player_red_id,
            'is_vs_ai' => $game->is_vs_ai,
            'ai_difficulty' => $game->ai_difficulty,
            'use_llm' => $game->use_llm,
        ]);

        broadcast(new GameCreated($game))->toOthers();

        return response()->json($game, 201);
    }

    /**
     * Join an existing game
     */
    public function join(Game $game): JsonResponse
    {
        $userId = Auth::id();

        $game = DB::transaction(function () use ($game, $userId) {
            $lockedGame = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();

            if ($lockedGame->status !== GameStatus::WAITING || $lockedGame->player_blue_id !== null) {
                return response()->json(['error' => 'Game is not available to join'], 400);
            }

            if ($lockedGame->player_red_id === $userId) {
                return response()->json(['error' => 'Cannot join your own game'], 400);
            }

            if ($lockedGame->is_vs_ai) {
                return response()->json(['error' => 'Cannot join AI game'], 400);
            }

            $lockedGame->player_blue_id = $userId;
            $lockedGame->status = GameStatus::SETUP;
            $lockedGame->save();

            return $lockedGame;
        });

        if ($game instanceof JsonResponse) {
            return $game;
        }

        Log::info('Game joined', [
            'game_id' => $game->id,
            'player_red_id' => $game->player_red_id,
            'player_blue_id' => $game->player_blue_id,
        ]);

        broadcast(new GameStarted($game));

        return response()->json($game);
    }

    /**
     * Get game state
     */
    public function show(Game $game): JsonResponse
    {
        $userId = Auth::id();
        $playerColor = $game->getPlayerColor($userId);

        if (!$playerColor) {
            return response()->json(['error' => 'Not a participant in this game'], 403);
        }

        $boardForPlayer = $this->gameService->getBoardForPlayer($game->board_state, $playerColor);

        return response()->json([
            'game' => $game,
            'board' => $boardForPlayer,
            'player_color' => $playerColor,
            'is_my_turn' => $game->isPlayerTurn($userId) || ($game->is_vs_ai && $game->current_turn === PlayerColor::RED),
        ]);
    }

    /**
     * Submit initial piece setup
     */
    public function setup(Request $request, Game $game): JsonResponse
    {
        $userId = Auth::id();

        $validated = $request->validate([
            'pieces' => 'required|array',
            'pieces.*.row' => 'required|integer|min:0|max:9',
            'pieces.*.col' => 'required|integer|min:0|max:9',
            'pieces.*.rank' => 'required|integer|min:0|max:11',
        ]);

        $result = DB::transaction(function () use ($game, $userId, $validated) {
            $lockedGame = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();
            $playerColor = $lockedGame->getPlayerColor($userId);

            if (!$playerColor) {
                return response()->json(['error' => 'Not a participant in this game'], 403);
            }

            if ($lockedGame->status !== GameStatus::SETUP) {
                return response()->json(['error' => 'Game is not in setup phase'], 400);
            }

            if (($playerColor === PlayerColor::RED && $lockedGame->red_setup_complete) ||
                ($playerColor === PlayerColor::BLUE && $lockedGame->blue_setup_complete)) {
                return response()->json(['error' => 'Setup already submitted'], 400);
            }

            if (!$this->gameService->validateSetup($validated['pieces'], $playerColor)) {
                return response()->json(['error' => 'Invalid piece setup'], 400);
            }

            $lockedGame->board_state = $this->gameService->placePieces(
                $lockedGame->board_state,
                $validated['pieces'],
                $playerColor
            );

            if ($playerColor === PlayerColor::RED) {
                $lockedGame->red_setup_complete = true;
            } else {
                $lockedGame->blue_setup_complete = true;
            }

            if ($lockedGame->is_vs_ai && $playerColor === PlayerColor::RED) {
                $aiPieces = $this->aiService->generateSetup(PlayerColor::BLUE);
                $lockedGame->board_state = $this->gameService->placePieces(
                    $lockedGame->board_state,
                    $aiPieces,
                    PlayerColor::BLUE
                );
                $lockedGame->blue_setup_complete = true;
            }

            if ($lockedGame->isSetupComplete()) {
                $lockedGame->status = GameStatus::IN_PROGRESS;
            }

            $lockedGame->save();

            return [
                'game' => $lockedGame,
                'player_color' => $playerColor,
            ];
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        $game = $result['game'];
        $playerColor = $result['player_color'];

        Log::info('Player setup completed', [
            'game_id' => $game->id,
            'player_id' => $userId,
            'player_color' => $playerColor->value,
            'is_vs_ai' => $game->is_vs_ai,
            'status' => $game->status->value,
            'red_setup_complete' => $game->red_setup_complete,
            'blue_setup_complete' => $game->blue_setup_complete,
        ]);

        broadcast(new SetupComplete($game, $playerColor))->toOthers();

        if ($game->status === GameStatus::IN_PROGRESS) {
            broadcast(new GameStarted($game));
        }

        return response()->json([
            'game' => $game,
            'board' => $this->gameService->getBoardForPlayer($game->board_state, $playerColor),
        ]);
    }

    /**
     * Make a move
     */
    public function move(Request $request, Game $game): JsonResponse
    {
        $userId = Auth::id();

        $validated = $request->validate([
            'from_row' => 'required|integer|min:0|max:9',
            'from_col' => 'required|integer|min:0|max:9',
            'to_row' => 'required|integer|min:0|max:9',
            'to_col' => 'required|integer|min:0|max:9',
        ]);

        $transactionResult = DB::transaction(function () use ($game, $userId, $validated) {
            $lockedGame = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();
            $playerColor = $lockedGame->getPlayerColor($userId);

            if (!$playerColor) {
                return response()->json(['error' => 'Not a participant in this game'], 403);
            }

            if ($lockedGame->status !== GameStatus::IN_PROGRESS) {
                return response()->json(['error' => 'Game is not in progress'], 400);
            }

            if ($lockedGame->current_turn !== $playerColor) {
                return response()->json(['error' => 'Not your turn'], 400);
            }

            if (!$this->gameService->validateMove(
                $lockedGame,
                $validated['from_row'],
                $validated['from_col'],
                $validated['to_row'],
                $validated['to_col'],
                $playerColor
            )) {
                Log::warning('Invalid player move attempted', [
                    'game_id' => $lockedGame->id,
                    'player_id' => $userId,
                    'player_color' => $playerColor->value,
                    'from' => ['row' => $validated['from_row'], 'col' => $validated['from_col']],
                    'to' => ['row' => $validated['to_row'], 'col' => $validated['to_col']],
                ]);

                return response()->json(['error' => 'Invalid move'], 400);
            }

            $moveResult = $this->gameService->executeMove(
                $lockedGame,
                $validated['from_row'],
                $validated['from_col'],
                $validated['to_row'],
                $validated['to_col'],
                $playerColor
            );

            return [
                'game' => $lockedGame->fresh(),
                'result' => $moveResult,
                'player_color' => $playerColor,
            ];
        });

        if ($transactionResult instanceof JsonResponse) {
            return $transactionResult;
        }

        $game = $transactionResult['game'];
        $result = $transactionResult['result'];
        $playerColor = $transactionResult['player_color'];

        Log::info('Player move executed', [
            'game_id' => $game->id,
            'player_id' => $userId,
            'player_color' => $playerColor->value,
            'from' => ['row' => $validated['from_row'], 'col' => $validated['from_col']],
            'to' => ['row' => $validated['to_row'], 'col' => $validated['to_col']],
            'result' => $result['type'],
            'captured' => $result['captured'] !== null,
            'winner' => $result['winner']?->value,
            'next_turn' => $game->current_turn?->value,
            'status' => $game->status->value,
        ]);

        broadcast(new MoveMade($game, $result, $playerColor))->toOthers();

        if ($game->status === GameStatus::FINISHED) {
            broadcast(new GameUpdated($game));
        }

        $boardAfterPlayerMove = $this->gameService->getBoardForPlayer($game->board_state, $playerColor);
        $aiPending = $game->is_vs_ai && $game->status === GameStatus::IN_PROGRESS && $game->current_turn === PlayerColor::BLUE;

        return response()->json([
            'game' => $game,
            'board' => $boardAfterPlayerMove,
            'result' => $result,
            'ai_pending' => $aiPending,
        ]);
    }

    /**
     * Request AI move (separate endpoint to allow frontend to show "thinking" state)
     */
    public function aiMove(Game $game): JsonResponse
    {
        $userId = Auth::id();

        $transactionResult = DB::transaction(function () use ($game, $userId) {
            $lockedGame = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();

            if (!$lockedGame->is_vs_ai) {
                return response()->json(['error' => 'Not an AI game'], 400);
            }

            if ($lockedGame->player_red_id !== $userId) {
                return response()->json(['error' => 'Not a participant in this game'], 403);
            }

            if ($lockedGame->status !== GameStatus::IN_PROGRESS) {
                return response()->json(['error' => 'Game is not in progress'], 400);
            }

            if ($lockedGame->current_turn !== PlayerColor::BLUE) {
                return response()->json(['error' => 'Not AI turn'], 400);
            }

            $aiMove = $this->aiService->makeMove($lockedGame, $lockedGame->use_llm);

            if (!$aiMove) {
                Log::warning('AI could not select a move', [
                    'game_id' => $lockedGame->id,
                    'use_llm' => $lockedGame->use_llm,
                    'ai_difficulty' => $lockedGame->ai_difficulty,
                ]);

                return response()->json(['error' => 'AI could not make a move'], 500);
            }

            $aiResult = $this->gameService->executeMove(
                $lockedGame,
                $aiMove['from']['row'],
                $aiMove['from']['col'],
                $aiMove['to']['row'],
                $aiMove['to']['col'],
                PlayerColor::BLUE
            );

            return [
                'game' => $lockedGame->fresh(),
                'ai_move' => $aiMove,
                'ai_result' => $aiResult,
            ];
        });

        if ($transactionResult instanceof JsonResponse) {
            return $transactionResult;
        }

        $game = $transactionResult['game'];
        $aiMove = $transactionResult['ai_move'];
        $aiResult = $transactionResult['ai_result'];

        Log::info('AI move executed', [
            'game_id' => $game->id,
            'use_llm' => $game->use_llm,
            'ai_difficulty' => $game->ai_difficulty,
            'from' => $aiMove['from'],
            'to' => $aiMove['to'],
            'result' => $aiResult['type'],
            'captured' => $aiResult['captured'] !== null,
            'winner' => $aiResult['winner']?->value,
            'next_turn' => $game->current_turn?->value,
            'status' => $game->status->value,
        ]);

        broadcast(new MoveMade($game, $aiResult, PlayerColor::BLUE))->toOthers();

        if ($game->status === GameStatus::FINISHED) {
            broadcast(new GameUpdated($game));
        }

        return response()->json([
            'game' => $game,
            'board' => $this->gameService->getBoardForPlayer($game->board_state, PlayerColor::RED),
            'ai_move' => $aiMove,
            'ai_result' => $aiResult,
        ]);
    }

    /**
     * Get valid moves for a piece
     */
    public function validMoves(Request $request, Game $game): JsonResponse
    {
        $userId = Auth::id();
        $playerColor = $game->getPlayerColor($userId);

        if ($game->is_vs_ai && $game->player_red_id === $userId) {
            $playerColor = PlayerColor::RED;
        }

        if (!$playerColor) {
            return response()->json(['error' => 'Not a participant in this game'], 403);
        }

        $validated = $request->validate([
            'row' => 'required|integer|min:0|max:9',
            'col' => 'required|integer|min:0|max:9',
        ]);

        $board = $game->board_state;
        $piece = $board[$validated['row']][$validated['col']] ?? null;

        if (!$piece || ($piece['color'] ?? null) !== $playerColor->value) {
            return response()->json(['moves' => []]);
        }

        $allMoves = $this->gameService->getValidMoves($board, $playerColor);
        $pieceMoves = array_filter($allMoves, fn($m) =>
            $m['from']['row'] === $validated['row'] && $m['from']['col'] === $validated['col']
        );

        return response()->json(['moves' => array_values($pieceMoves)]);
    }

    /**
     * Forfeit a game
     */
    public function forfeit(Game $game): JsonResponse
    {
        $userId = Auth::id();

        $result = DB::transaction(function () use ($game, $userId) {
            $lockedGame = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();
            $playerColor = $lockedGame->getPlayerColor($userId);

            if (!$playerColor) {
                return response()->json(['error' => 'Not a participant in this game'], 403);
            }

            if ($lockedGame->status !== GameStatus::IN_PROGRESS && $lockedGame->status !== GameStatus::SETUP) {
                return response()->json(['error' => 'Game cannot be forfeited'], 400);
            }

            $lockedGame->status = GameStatus::FINISHED;
            $lockedGame->winner = $playerColor === PlayerColor::RED ? PlayerColor::BLUE : PlayerColor::RED;
            $lockedGame->save();

            return $lockedGame;
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        broadcast(new GameUpdated($result));

        return response()->json($result);
    }
}
