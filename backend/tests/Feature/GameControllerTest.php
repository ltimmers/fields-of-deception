<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Enums\PlayerColor;
use App\Models\Game;
use App\Models\User;
use App\Services\GameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    public function test_user_can_list_their_games(): void
    {
        $otherUser = User::factory()->create();
        
        // Create games where user is red player
        $gameAsRed = Game::factory()->create(['player_red_id' => $this->user->id]);
        
        // Create games where user is blue player
        $gameAsBlue = Game::factory()->create(['player_blue_id' => $this->user->id]);
        
        // Create game where user is not involved
        Game::factory()->create(['player_red_id' => $otherUser->id]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/games');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonFragment(['id' => $gameAsRed->id])
            ->assertJsonFragment(['id' => $gameAsBlue->id]);
    }

    public function test_user_can_list_open_games(): void
    {
        // Create open games
        $openGame1 = Game::factory()->create([
            'status' => GameStatus::WAITING,
            'is_vs_ai' => false,
            'player_blue_id' => null,
        ]);

        $openGame2 = Game::factory()->create([
            'status' => GameStatus::WAITING,
            'is_vs_ai' => false,
            'player_blue_id' => null,
        ]);

        // Create games that should not appear in the list
        Game::factory()->create(['status' => GameStatus::IN_PROGRESS]); // Not waiting
        Game::factory()->create(['is_vs_ai' => true]); // AI game
        Game::factory()->withBluePlayer()->create(); // Has blue player

        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/games/open');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonFragment(['id' => $openGame1->id])
            ->assertJsonFragment(['id' => $openGame2->id]);
    }

    public function test_user_can_create_pvp_game(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/games', [
                'vs_ai' => false,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'player_red_id',
                'status',
                'current_turn',
                'board_state',
                'is_vs_ai',
            ])
            ->assertJson([
                'player_red_id' => $this->user->id,
                'status' => GameStatus::WAITING->value,
                'is_vs_ai' => false,
            ]);

        $this->assertDatabaseHas('games', [
            'player_red_id' => $this->user->id,
            'status' => GameStatus::WAITING->value,
        ]);
    }

    public function test_user_can_create_ai_game(): void
    {
        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/games', [
                'vs_ai' => true,
                'ai_difficulty' => 'hard',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'player_red_id' => $this->user->id,
                'status' => GameStatus::SETUP->value,
                'is_vs_ai' => true,
                'ai_difficulty' => 'hard',
            ]);
    }

    public function test_user_can_join_open_game(): void
    {
        $otherUser = User::factory()->create();
        $game = Game::factory()->create([
            'player_red_id' => $otherUser->id,
            'status' => GameStatus::WAITING,
            'is_vs_ai' => false,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/games/{$game->id}/join");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $game->id,
                'player_blue_id' => $this->user->id,
                'status' => GameStatus::SETUP->value,
            ]);

        $this->assertDatabaseHas('games', [
            'id' => $game->id,
            'player_blue_id' => $this->user->id,
            'status' => GameStatus::SETUP->value,
        ]);
    }

    public function test_user_cannot_join_own_game(): void
    {
        $game = Game::factory()->create([
            'player_red_id' => $this->user->id,
            'status' => GameStatus::WAITING,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/games/{$game->id}/join");

        $response->assertStatus(400)
            ->assertJson(['error' => 'Cannot join your own game']);
    }

    public function test_user_cannot_join_ai_game(): void
    {
        $otherUser = User::factory()->create();
        $game = Game::factory()->vsAi()->create([
            'player_red_id' => $otherUser->id,
            'status' => GameStatus::WAITING,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/games/{$game->id}/join");

        $response->assertStatus(400)
            ->assertJson(['error' => 'Cannot join AI game']);
    }

    public function test_user_cannot_join_game_not_waiting(): void
    {
        $otherUser = User::factory()->create();
        $game = Game::factory()->create([
            'player_red_id' => $otherUser->id,
            'status' => GameStatus::IN_PROGRESS,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/games/{$game->id}/join");

        $response->assertStatus(400)
            ->assertJson(['error' => 'Game is not available to join']);
    }

    public function test_user_can_view_game_as_participant(): void
    {
        $game = Game::factory()->create([
            'player_red_id' => $this->user->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/games/{$game->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'game',
                'board',
                'player_color',
                'is_my_turn',
            ])
            ->assertJson([
                'player_color' => PlayerColor::RED->value,
            ]);
    }

    public function test_user_cannot_view_game_as_non_participant(): void
    {
        $otherUser = User::factory()->create();
        $game = Game::factory()->create([
            'player_red_id' => $otherUser->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->getJson("/api/games/{$game->id}");

        $response->assertStatus(403)
            ->assertJson(['error' => 'Not a participant in this game']);
    }

    public function test_user_can_complete_setup(): void
    {
        $gameService = new GameService();
        $game = Game::factory()->create([
            'player_red_id' => $this->user->id,
            'status' => GameStatus::SETUP,
        ]);

        // Generate valid piece setup for red player (rows 6-9)
        $pieces = $this->generateValidSetup(PlayerColor::RED);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/games/{$game->id}/setup", [
                'pieces' => $pieces,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['game', 'board']);

        $this->assertDatabaseHas('games', [
            'id' => $game->id,
            'red_setup_complete' => true,
        ]);
    }

    public function test_setup_fails_with_invalid_piece_count(): void
    {
        $game = Game::factory()->create([
            'player_red_id' => $this->user->id,
            'status' => GameStatus::SETUP,
        ]);

        // Only send 10 pieces instead of 40
        $pieces = array_map(fn($i) => [
            'row' => 6,
            'col' => $i,
            'rank' => 2,
        ], range(0, 9));

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/games/{$game->id}/setup", [
                'pieces' => $pieces,
            ]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Invalid piece setup']);
    }

    public function test_setup_fails_when_not_in_setup_phase(): void
    {
        $game = Game::factory()->inProgress()->create([
            'player_red_id' => $this->user->id,
        ]);

        $pieces = $this->generateValidSetup(PlayerColor::RED);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/games/{$game->id}/setup", [
                'pieces' => $pieces,
            ]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Game is not in setup phase']);
    }

    public function test_user_can_make_valid_move(): void
    {
        $game = Game::factory()->inProgress()->create([
            'player_red_id' => $this->user->id,
            'current_turn' => PlayerColor::RED,
        ]);

        // Place a scout at position (6, 0) that can move
        $board = $game->board_state;
        $board[6][0] = ['color' => 'red', 'rank' => 2]; // Scout
        $game->board_state = $board;
        $game->save();

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/games/{$game->id}/move", [
                'from_row' => 6,
                'from_col' => 0,
                'to_row' => 5,
                'to_col' => 0,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['game', 'board', 'result']);
    }

    public function test_move_fails_when_not_players_turn(): void
    {
        $otherUser = User::factory()->create();
        $game = Game::factory()->inProgress()->create([
            'player_red_id' => $this->user->id,
            'player_blue_id' => $otherUser->id,
            'current_turn' => PlayerColor::BLUE,
        ]);

        $board = $game->board_state;
        $board[6][0] = ['color' => 'red', 'rank' => 2];
        $game->board_state = $board;
        $game->save();

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/games/{$game->id}/move", [
                'from_row' => 6,
                'from_col' => 0,
                'to_row' => 5,
                'to_col' => 0,
            ]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Not your turn']);
    }

    public function test_move_fails_when_game_not_in_progress(): void
    {
        $game = Game::factory()->create([
            'player_red_id' => $this->user->id,
            'status' => GameStatus::SETUP,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/games/{$game->id}/move", [
                'from_row' => 6,
                'from_col' => 0,
                'to_row' => 5,
                'to_col' => 0,
            ]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Game is not in progress']);
    }

    public function test_user_can_get_valid_moves_for_piece(): void
    {
        $game = Game::factory()->inProgress()->create([
            'player_red_id' => $this->user->id,
            'current_turn' => PlayerColor::RED,
        ]);

        // Place a piece
        $board = $game->board_state;
        $board[6][0] = ['color' => 'red', 'rank' => 2]; // Scout
        $game->board_state = $board;
        $game->save();

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/games/{$game->id}/valid-moves", [
                'row' => 6,
                'col' => 0,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['moves']);
    }

    public function test_valid_moves_returns_empty_for_opponents_piece(): void
    {
        $otherUser = User::factory()->create();
        $game = Game::factory()->inProgress()->create([
            'player_red_id' => $this->user->id,
            'player_blue_id' => $otherUser->id,
        ]);

        // Place opponent's piece
        $board = $game->board_state;
        $board[2][0] = ['color' => 'blue', 'rank' => 2];
        $game->board_state = $board;
        $game->save();

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/games/{$game->id}/valid-moves", [
                'row' => 2,
                'col' => 0,
            ]);

        $response->assertStatus(200)
            ->assertJson(['moves' => []]);
    }

    public function test_user_can_forfeit_game(): void
    {
        $otherUser = User::factory()->create();
        $game = Game::factory()->inProgress()->create([
            'player_red_id' => $this->user->id,
            'player_blue_id' => $otherUser->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/games/{$game->id}/forfeit");

        $response->assertStatus(200)
            ->assertJson([
                'id' => $game->id,
                'status' => GameStatus::FINISHED->value,
                'winner' => PlayerColor::BLUE->value,
            ]);

        $this->assertDatabaseHas('games', [
            'id' => $game->id,
            'status' => GameStatus::FINISHED->value,
            'winner' => PlayerColor::BLUE->value,
        ]);
    }

    public function test_forfeit_fails_for_non_participant(): void
    {
        $otherUser = User::factory()->create();
        $game = Game::factory()->inProgress()->create([
            'player_red_id' => $otherUser->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/games/{$game->id}/forfeit");

        $response->assertStatus(403)
            ->assertJson(['error' => 'Not a participant in this game']);
    }

    public function test_forfeit_fails_when_game_finished(): void
    {
        $game = Game::factory()->finished()->create([
            'player_red_id' => $this->user->id,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson("/api/games/{$game->id}/forfeit");

        $response->assertStatus(400)
            ->assertJson(['error' => 'Game cannot be forfeited']);
    }

    public function test_endpoints_require_authentication(): void
    {
        $game = Game::factory()->create();

        $this->getJson('/api/games')->assertStatus(401);
        $this->getJson('/api/games/open')->assertStatus(401);
        $this->postJson('/api/games')->assertStatus(401);
        $this->getJson("/api/games/{$game->id}")->assertStatus(401);
        $this->postJson("/api/games/{$game->id}/join")->assertStatus(401);
        $this->postJson("/api/games/{$game->id}/setup")->assertStatus(401);
        $this->postJson("/api/games/{$game->id}/move")->assertStatus(401);
        $this->postJson("/api/games/{$game->id}/valid-moves")->assertStatus(401);
        $this->postJson("/api/games/{$game->id}/forfeit")->assertStatus(401);
    }

    /**
     * Generate a valid piece setup for testing
     */
    private function generateValidSetup(PlayerColor $color): array
    {
        $pieces = [];
        $startRow = $color === PlayerColor::RED ? 6 : 0;
        
        // Piece distribution according to game rules
        $ranks = [
            10 => 1,  // Marshal
            9 => 1,   // General
            8 => 2,   // Colonel
            7 => 3,   // Major
            6 => 4,   // Captain
            5 => 4,   // Lieutenant
            4 => 4,   // Sergeant
            3 => 5,   // Miner
            2 => 8,   // Scout
            1 => 1,   // Spy
            11 => 6,  // Bomb
            0 => 1,   // Flag
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
}
