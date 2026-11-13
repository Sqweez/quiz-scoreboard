<?php

namespace App\Console\Commands;

use App\Services\SupabaseTransfer;
use DomainException;
use Illuminate\Console\Command;
use PDOException;

class ImportSupabase extends Command
{
    protected $signature = 'supabase:import {--verify-only : Compare every mapped field without writing target records}';

    protected $description = 'Import Supabase users and games into an empty PostgreSQL database, then verify every field';

    public function handle(SupabaseTransfer $transfer): int
    {
        try {
            $counts = $transfer->run((bool) $this->option('verify-only'));
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (PDOException) {
            // Database exceptions can contain credentials, rows and password hashes.
            $this->error('Transfer failed; all target changes were rolled back. Check connection access and source/target schema privately.');

            return self::FAILURE;
        }

        foreach ($counts as $table => $count) {
            $this->line($table.': '.$count.' verified');
        }
        $this->info($this->option('verify-only') ? 'Verification complete; no target records changed.' : 'Import committed after full field verification.');

        return self::SUCCESS;
    }
}
