<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Existing values were written in UTC; keep the same instants while attaching the zone.
        DB::statement("ALTER TABLE games
            ALTER COLUMN created_at TYPE timestamptz(3) USING created_at AT TIME ZONE 'UTC',
            ALTER COLUMN updated_at TYPE timestamptz(3) USING updated_at AT TIME ZONE 'UTC'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE games
            ALTER COLUMN created_at TYPE timestamp(3) USING created_at AT TIME ZONE 'UTC',
            ALTER COLUMN updated_at TYPE timestamp(3) USING updated_at AT TIME ZONE 'UTC'");
    }
};
