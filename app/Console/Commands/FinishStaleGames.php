<?php

namespace App\Console\Commands;

use App\Models\Game;
use Illuminate\Console\Command;

class FinishStaleGames extends Command
{
    protected $signature = 'games:finish-stale';

    protected $description = 'Finish open games whose last round has scores and that have been idle for at least two hours';

    public function handle(): int
    {
        // A game counts as played when its last round (by position) has at least one non-zero score.
        $count = Game::where('status', '!=', 'finished')
            ->where('updated_at', '<', now()->subHours(2))
            ->whereExists(fn ($query) => $query->selectRaw('1')
                ->from('scores')
                ->join('rounds', 'rounds.id', '=', 'scores.round_id')
                ->whereColumn('rounds.game_id', 'games.id')
                ->where('scores.value', '!=', 0)
                ->whereRaw('rounds.position = (select max(last_round.position) from rounds as last_round where last_round.game_id = games.id)'))
            ->update(['status' => 'finished']);

        $this->info("Finished {$count} game(s).");

        return self::SUCCESS;
    }
}
