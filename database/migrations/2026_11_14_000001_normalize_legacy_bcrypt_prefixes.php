<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // $2a$/$2b$ hashes from Supabase are byte-identical bcrypt to PHP's $2y$; only the prefix differs.
        DB::statement("UPDATE users SET password = '\$2y\$' || substr(password, 5) WHERE password ~ '^\\\$2[ab]\\\$'");
    }

    public function down(): void
    {
        // Original prefixes are not recoverable.
    }
};
