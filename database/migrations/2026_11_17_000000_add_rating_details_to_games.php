<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            // Snapshot of the linked Quiz Please game: title, gameNumber, packageNumber, date (wall-clock, no time zone).
            $table->jsonb('extra')->nullable();
            // Category id from the Quiz Please rating API, chosen by the user.
            $table->unsignedSmallInteger('rating_category_id')->nullable();
            $table->boolean('rating_include_scores')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['extra', 'rating_category_id', 'rating_include_scores']);
        });
    }
};
