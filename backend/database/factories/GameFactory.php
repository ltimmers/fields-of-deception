<?php

namespace Database\Factories;

use App\Enums\GameStatus;
use App\Enums\PlayerColor;
use App\Models\Game;
use App\Models\User;
use App\Services\GameService;
use Illuminate\Database\Eloquent\Factories\Factory;

class GameFactory extends Factory
{
    protected $model = Game::class;

    public function definition(): array
    {
        $gameService = new GameService();
        
        return [
            'player_red_id' => User::factory(),
            'player_blue_id' => null,
            'status' => GameStatus::WAITING,
            'current_turn' => PlayerColor::RED,
            'winner' => null,
            'board_state' => $gameService->createEmptyBoard(),
            'is_vs_ai' => false,
            'ai_difficulty' => 'medium',
            'red_setup_complete' => false,
            'blue_setup_complete' => false,
        ];
    }

    public function withBluePlayer(): static
    {
        return $this->state(fn (array $attributes) => [
            'player_blue_id' => User::factory(),
            'status' => GameStatus::SETUP,
        ]);
    }

    public function vsAi(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_vs_ai' => true,
            'status' => GameStatus::SETUP,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GameStatus::IN_PROGRESS,
            'red_setup_complete' => true,
            'blue_setup_complete' => true,
        ]);
    }

    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GameStatus::FINISHED,
            'winner' => fake()->randomElement([PlayerColor::RED, PlayerColor::BLUE]),
            'red_setup_complete' => true,
            'blue_setup_complete' => true,
        ]);
    }
}
