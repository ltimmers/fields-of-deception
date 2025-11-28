<?php

use App\Enums\GameStatus;
use App\Enums\PlayerColor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_red_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('player_blue_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('status')->default(GameStatus::WAITING->value);
            $table->string('current_turn')->default(PlayerColor::RED->value);
            $table->string('winner')->nullable();
            $table->json('board_state')->nullable();
            $table->boolean('is_vs_ai')->default(false);
            $table->string('ai_difficulty')->default('medium');
            $table->boolean('red_setup_complete')->default(false);
            $table->boolean('blue_setup_complete')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
