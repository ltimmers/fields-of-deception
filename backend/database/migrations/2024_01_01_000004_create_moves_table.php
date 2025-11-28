<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->onDelete('cascade');
            $table->string('player_color');
            $table->integer('piece_rank');
            $table->integer('from_row');
            $table->integer('from_col');
            $table->integer('to_row');
            $table->integer('to_col');
            $table->integer('captured_rank')->nullable();
            $table->string('result')->nullable(); // win, lose, draw, move
            $table->integer('move_number');
            $table->timestamps();

            $table->index(['game_id', 'move_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moves');
    }
};
