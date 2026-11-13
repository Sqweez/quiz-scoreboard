<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // UUID of the source game in Quiz Please; existing games keep NULL.
            $table->uuid('external_id')->nullable();
            $table->unique(['user_id', 'external_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            // Encrypted Quiz Please API access token of this user.
            $table->text('quizplease_token')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'external_id']);
            $table->dropColumn('external_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('quizplease_token');
        });
    }
};
