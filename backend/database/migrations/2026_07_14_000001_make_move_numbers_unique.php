<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('moves', function (Blueprint $table) {
            $table->index('game_id');
            $table->dropIndex(['game_id', 'move_number']);
            $table->unique(['game_id', 'move_number']);
        });
    }

    public function down(): void
    {
        Schema::table('moves', function (Blueprint $table) {
            $table->dropUnique(['game_id', 'move_number']);
            $table->index(['game_id', 'move_number']);
            $table->dropIndex(['game_id']);
        });
    }
};
