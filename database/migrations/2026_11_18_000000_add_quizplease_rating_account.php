<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Token and city code of the user's account in the Quiz Please rating module (rating-api.quizplease.ru).
            $table->text('quizplease_rating_token')->nullable();
            $table->string('quizplease_rating_city')->nullable();
        });

        Schema::table('games', function (Blueprint $table) {
            // Set once the game has been created in the rating module, so it is not created twice.
            $table->timestamp('rating_created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['quizplease_rating_token', 'quizplease_rating_city']);
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn('rating_created_at');
        });
    }
};
