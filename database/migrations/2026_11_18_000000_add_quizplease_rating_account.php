<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Token and city (code and id) of the user's account in the Quiz Please rating module (rating-api.quizplease.ru).
            $table->text('quizplease_rating_token')->nullable();
            $table->string('quizplease_rating_city')->nullable();
            $table->unsignedInteger('quizplease_rating_city_id')->nullable();
        });

        Schema::table('games', function (Blueprint $table) {
            // Package name in the rating module; free text there, typed by the user.
            $table->string('rating_package')->nullable();
            // Set once the game has been created in the rating module, so it is not created twice.
            $table->timestamp('rating_created_at')->nullable();
            // Set once the results table has been queued for upload in the rating module.
            $table->timestamp('rating_uploaded_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['quizplease_rating_token', 'quizplease_rating_city', 'quizplease_rating_city_id']);
        });

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['rating_package', 'rating_created_at', 'rating_uploaded_at']);
        });
    }
};
