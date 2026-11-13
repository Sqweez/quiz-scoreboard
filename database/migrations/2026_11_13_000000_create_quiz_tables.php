<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->text('title');
            $table->string('status')->default('draft')->index();
            $table->timestamps(3);
            $table->index('user_id');
        });
        Schema::create('rounds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained()->cascadeOnDelete()->index();
            $table->text('title');
            $table->double('max_score')->nullable();
            $table->integer('questions_count')->nullable();
            $table->integer('position');
        });
        Schema::create('teams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('game_id')->constrained()->cascadeOnDelete()->index();
            $table->text('name');
            $table->integer('position');
        });
        Schema::create('scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('round_id')->constrained()->cascadeOnDelete()->index();
            $table->double('value')->default(0);
            $table->unique(['team_id', 'round_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scores');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('rounds');
        Schema::dropIfExists('games');
    }
};
