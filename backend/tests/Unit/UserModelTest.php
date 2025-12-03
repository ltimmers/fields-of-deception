<?php

namespace Tests\Unit;

use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_games_as_red_player(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create(['player_red_id' => $user->id]);

        $games = $user->gamesAsRed;

        $this->assertCount(1, $games);
        $this->assertEquals($game->id, $games->first()->id);
    }

    public function test_user_has_games_as_blue_player(): void
    {
        $user = User::factory()->create();
        $game = Game::factory()->create(['player_blue_id' => $user->id]);

        $games = $user->gamesAsBlue;

        $this->assertCount(1, $games);
        $this->assertEquals($game->id, $games->first()->id);
    }

    public function test_user_can_be_in_multiple_games(): void
    {
        $user = User::factory()->create();
        $game1 = Game::factory()->create(['player_red_id' => $user->id]);
        $game2 = Game::factory()->create(['player_blue_id' => $user->id]);
        $game3 = Game::factory()->create(['player_red_id' => $user->id]);

        $gamesAsRed = $user->gamesAsRed;
        $gamesAsBlue = $user->gamesAsBlue;

        $this->assertCount(2, $gamesAsRed);
        $this->assertCount(1, $gamesAsBlue);
    }

    public function test_user_password_is_hidden(): void
    {
        $user = User::factory()->create();

        $userArray = $user->toArray();

        $this->assertArrayNotHasKey('password', $userArray);
    }

    public function test_user_remember_token_is_hidden(): void
    {
        $user = User::factory()->create();

        $userArray = $user->toArray();

        $this->assertArrayNotHasKey('remember_token', $userArray);
    }

    public function test_user_has_required_fillable_fields(): void
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ];

        $user = User::create($userData);

        $this->assertEquals('Test User', $user->name);
        $this->assertEquals('test@example.com', $user->email);
        $this->assertNotEmpty($user->password);
    }

    public function test_user_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        User::factory()->create(['email' => 'test@example.com']);
    }
}
