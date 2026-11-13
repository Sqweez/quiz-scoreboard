<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE games ADD COLUMN finished_at timestamptz(3) NULL');
        // Finished games are read-only, so their last update is the best known finish time.
        DB::statement("UPDATE games SET finished_at = updated_at WHERE status = 'finished'");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE games DROP COLUMN finished_at');
    }
};
