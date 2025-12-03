<?php

namespace Tests\Unit;

use App\Enums\GameStatus;
use App\Enums\PlayerColor;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_game_belongs_to_player_red(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create(['player_red_id' => $user->id]);

        $this->assertInstanceOf(User::class, $game->playerRed);
        $this->assertEquals($user->id, $game->playerRed->id);
    }

    public function test_game_belongs_to_player_blue(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create(['player_blue_id' => $user->id]);

        $this->assertInstanceOf(User::class, $game->playerBlue);
        $this->assertEquals($user->id, $game->playerBlue->id);
    }

    public function test_is_player_turn_returns_true_for_red_player_on_red_turn(): void
    {
        $redPlayer = User::factory()->create();
        $bluePlayer = User::factory()->create();
        
        $game = Game::factory()->create([
            'player_red_id' => $redPlayer->id,
            'player_blue_id' => $bluePlayer->id,
            'current_turn' => PlayerColor::RED,
        ]);

        $this->assertTrue($game->isPlayerTurn($redPlayer->id));
    }

    public function test_is_player_turn_returns_false_for_red_player_on_blue_turn(): void
    {
        $redPlayer = User::factory()->create();
        $bluePlayer = User::factory()->create();
        
        $game = Game::factory()->create([
            'player_red_id' => $redPlayer->id,
            'player_blue_id' => $bluePlayer->id,
            'current_turn' => PlayerColor::BLUE,
        ]);

        $this->assertFalse($game->isPlayerTurn($redPlayer->id));
    }

    public function test_is_player_turn_returns_true_for_blue_player_on_blue_turn(): void
    {
        $redPlayer = User::factory()->create();
        $bluePlayer = User::factory()->create();
        
        $game = Game::factory()->create([
            'player_red_id' => $redPlayer->id,
            'player_blue_id' => $bluePlayer->id,
            'current_turn' => PlayerColor::BLUE,
        ]);

        $this->assertTrue($game->isPlayerTurn($bluePlayer->id));
    }

    public function test_is_player_turn_returns_false_for_blue_player_on_red_turn(): void
    {
        $redPlayer = User::factory()->create();
        $bluePlayer = User::factory()->create();
        
        $game = Game::factory()->create([
            'player_red_id' => $redPlayer->id,
            'player_blue_id' => $bluePlayer->id,
            'current_turn' => PlayerColor::RED,
        ]);

        $this->assertFalse($game->isPlayerTurn($bluePlayer->id));
    }

    public function test_get_player_color_returns_red_for_red_player(): void
    {
        $redPlayer = User::factory()->create();
        $game = Game::factory()->create(['player_red_id' => $redPlayer->id]);

        $color = $game->getPlayerColor($redPlayer->id);

        $this->assertEquals(PlayerColor::RED, $color);
    }

    public function test_get_player_color_returns_blue_for_blue_player(): void
    {
        $bluePlayer = User::factory()->create();
        $game = Game::factory()->create(['player_blue_id' => $bluePlayer->id]);

        $color = $game->getPlayerColor($bluePlayer->id);

        $this->assertEquals(PlayerColor::BLUE, $color);
    }

    public function test_get_player_color_returns_null_for_non_participant(): void
    {
        $nonParticipant = User::factory()->create();
        $game = Game::factory()->create();

        $color = $game->getPlayerColor($nonParticipant->id);

        $this->assertNull($color);
    }

    public function test_switch_turn_changes_from_red_to_blue(): void
    {
        $game = Game::factory()->create(['current_turn' => PlayerColor::RED]);

        $game->switchTurn();

        $this->assertEquals(PlayerColor::BLUE, $game->current_turn);
    }

    public function test_switch_turn_changes_from_blue_to_red(): void
    {
        $game = Game::factory()->create(['current_turn' => PlayerColor::BLUE]);

        $game->switchTurn();

        $this->assertEquals(PlayerColor::RED, $game->current_turn);
    }

    public function test_is_setup_complete_returns_false_when_red_not_ready(): void
    {
        $game = Game::factory()->create([
            'red_setup_complete' => false,
            'blue_setup_complete' => true,
        ]);

        $this->assertFalse($game->isSetupComplete());
    }

    public function test_is_setup_complete_returns_false_when_blue_not_ready(): void
    {
        $game = Game::factory()->create([
            'red_setup_complete' => true,
            'blue_setup_complete' => false,
        ]);

        $this->assertFalse($game->isSetupComplete());
    }

    public function test_is_setup_complete_returns_false_when_neither_ready(): void
    {
        $game = Game::factory()->create([
            'red_setup_complete' => false,
            'blue_setup_complete' => false,
        ]);

        $this->assertFalse($game->isSetupComplete());
    }

    public function test_is_setup_complete_returns_true_when_both_ready(): void
    {
        $game = Game::factory()->create([
            'red_setup_complete' => true,
            'blue_setup_complete' => true,
        ]);

        $this->assertTrue($game->isSetupComplete());
    }

    public function test_game_has_correct_casts(): void
    {
        $game = Game::factory()->create([
            'status' => GameStatus::WAITING,
            'current_turn' => PlayerColor::RED,
            'is_vs_ai' => true,
            'red_setup_complete' => true,
            'blue_setup_complete' => false,
        ]);

        $this->assertInstanceOf(GameStatus::class, $game->status);
        $this->assertInstanceOf(PlayerColor::class, $game->current_turn);
        $this->assertIsBool($game->is_vs_ai);
        $this->assertIsBool($game->red_setup_complete);
        $this->assertIsBool($game->blue_setup_complete);
        $this->assertIsArray($game->board_state);
    }

    public function test_game_can_have_winner(): void
    {
        $game = Game::factory()->finished()->create([
            'winner' => PlayerColor::RED,
        ]);

        $this->assertInstanceOf(PlayerColor::class, $game->winner);
        $this->assertEquals(PlayerColor::RED, $game->winner);
    }

    public function test_game_winner_can_be_null(): void
    {
        $game = Game::factory()->create([
            'status' => GameStatus::IN_PROGRESS,
            'winner' => null,
        ]);

        $this->assertNull($game->winner);
    }
}
