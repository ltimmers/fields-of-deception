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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GameController extends Controller
{
    public function __construct(
        private readonly GameService $gameService,
        private readonly AIService $aiService
    ) {}

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
        ]);

        $game = new Game();
        $game->player_red_id = Auth::id();
        $game->status = GameStatus::WAITING;
        $game->current_turn = PlayerColor::RED;
        $game->board_state = $this->gameService->createEmptyBoard();
        $game->is_vs_ai = $validated['vs_ai'] ?? false;
        $game->ai_difficulty = $validated['ai_difficulty'] ?? 'medium';

        if ($game->is_vs_ai) {
            $game->status = GameStatus::SETUP;
        }

        $game->save();

        broadcast(new GameCreated($game))->toOthers();

        return response()->json($game, 201);
    }

    /**
     * Join an existing game
     */
    public function join(Game $game): JsonResponse
    {
        if ($game->status !== GameStatus::WAITING) {
            return response()->json(['error' => 'Game is not available to join'], 400);
        }

        if ($game->player_red_id === Auth::id()) {
            return response()->json(['error' => 'Cannot join your own game'], 400);
        }

        if ($game->is_vs_ai) {
            return response()->json(['error' => 'Cannot join AI game'], 400);
        }

        $game->player_blue_id = Auth::id();
        $game->status = GameStatus::SETUP;
        $game->save();

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

        if (!$playerColor && !$game->is_vs_ai) {
            return response()->json(['error' => 'Not a participant in this game'], 403);
        }

        // For AI games, player is always red
        if ($game->is_vs_ai) {
            $playerColor = PlayerColor::RED;
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
        $playerColor = $game->getPlayerColor($userId);

        // For AI games, player is always red
        if ($game->is_vs_ai && $game->player_red_id === $userId) {
            $playerColor = PlayerColor::RED;
        }

        if (!$playerColor) {
            return response()->json(['error' => 'Not a participant in this game'], 403);
        }

        if ($game->status !== GameStatus::SETUP) {
            return response()->json(['error' => 'Game is not in setup phase'], 400);
        }

        $validated = $request->validate([
            'pieces' => 'required|array',
            'pieces.*.row' => 'required|integer|min:0|max:9',
            'pieces.*.col' => 'required|integer|min:0|max:9',
            'pieces.*.rank' => 'required|integer|min:0|max:11',
        ]);

        if (!$this->gameService->validateSetup($validated['pieces'], $playerColor)) {
            return response()->json(['error' => 'Invalid piece setup'], 400);
        }

        $board = $this->gameService->placePieces($game->board_state, $validated['pieces'], $playerColor);
        $game->board_state = $board;

        if ($playerColor === PlayerColor::RED) {
            $game->red_setup_complete = true;
        } else {
            $game->blue_setup_complete = true;
        }

        // For AI games, set up AI pieces automatically
        if ($game->is_vs_ai && $playerColor === PlayerColor::RED) {
            $aiPieces = $this->aiService->generateSetup(PlayerColor::BLUE);
            $game->board_state = $this->gameService->placePieces($game->board_state, $aiPieces, PlayerColor::BLUE);
            $game->blue_setup_complete = true;
        }

        if ($game->isSetupComplete()) {
            $game->status = GameStatus::IN_PROGRESS;
        }

        $game->save();

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
        $playerColor = $game->getPlayerColor($userId);

        // For AI games, player is always red
        if ($game->is_vs_ai && $game->player_red_id === $userId) {
            $playerColor = PlayerColor::RED;
        }

        if (!$playerColor) {
            return response()->json(['error' => 'Not a participant in this game'], 403);
        }

        if ($game->status !== GameStatus::IN_PROGRESS) {
            return response()->json(['error' => 'Game is not in progress'], 400);
        }

        if ($game->current_turn !== $playerColor) {
            return response()->json(['error' => 'Not your turn'], 400);
        }

        $validated = $request->validate([
            'from_row' => 'required|integer|min:0|max:9',
            'from_col' => 'required|integer|min:0|max:9',
            'to_row' => 'required|integer|min:0|max:9',
            'to_col' => 'required|integer|min:0|max:9',
        ]);

        if (!$this->gameService->validateMove(
            $game,
            $validated['from_row'],
            $validated['from_col'],
            $validated['to_row'],
            $validated['to_col'],
            $playerColor
        )) {
            return response()->json(['error' => 'Invalid move'], 400);
        }

        $result = $this->gameService->executeMove(
            $game,
            $validated['from_row'],
            $validated['from_col'],
            $validated['to_row'],
            $validated['to_col'],
            $playerColor
        );

        broadcast(new MoveMade($game, $result, $playerColor))->toOthers();

        if ($game->status === GameStatus::FINISHED) {
            broadcast(new GameUpdated($game));
        }

        $response = [
            'game' => $game,
            'board' => $this->gameService->getBoardForPlayer($game->board_state, $playerColor),
            'result' => $result,
        ];

        // If AI game and game is still in progress, make AI move
        if ($game->is_vs_ai && $game->status === GameStatus::IN_PROGRESS && $game->current_turn === PlayerColor::BLUE) {
            $aiMove = $this->aiService->makeMove($game);

            if ($aiMove) {
                $aiResult = $this->gameService->executeMove(
                    $game,
                    $aiMove['from']['row'],
                    $aiMove['from']['col'],
                    $aiMove['to']['row'],
                    $aiMove['to']['col'],
                    PlayerColor::BLUE
                );

                $response['ai_move'] = $aiMove;
                $response['ai_result'] = $aiResult;
                $response['board'] = $this->gameService->getBoardForPlayer($game->board_state, $playerColor);
                $response['game'] = $game->fresh();
            }
        }

        return response()->json($response);
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
        $playerColor = $game->getPlayerColor($userId);

        if ($game->is_vs_ai && $game->player_red_id === $userId) {
            $playerColor = PlayerColor::RED;
        }

        if (!$playerColor) {
            return response()->json(['error' => 'Not a participant in this game'], 403);
        }

        if ($game->status !== GameStatus::IN_PROGRESS && $game->status !== GameStatus::SETUP) {
            return response()->json(['error' => 'Game cannot be forfeited'], 400);
        }

        $game->status = GameStatus::FINISHED;
        $game->winner = $playerColor === PlayerColor::RED ? PlayerColor::BLUE : PlayerColor::RED;
        $game->save();

        broadcast(new GameUpdated($game));

        return response()->json($game);
    }
}
