<?php

namespace App\Console\Commands;

use App\Models\ErrorOccurrence;
use Illuminate\Console\Command;

/**
 * Keeps error_occurrences from growing unbounded — this app has no queue
 * worker (see CLAUDE.md §7), so every occurrence is written synchronously
 * during the very request that already failed. A single misbehaving page
 * repeating a broken request thousands of times a day shouldn't leave a
 * table that grows forever; 60 days is plenty for the admin "Fehler-
 * Statistiken" page's own trend/top-paths view (App\Services\ErrorStats).
 */
class PruneErrorOccurrences extends Command
{
    protected $signature = 'app:prune-error-occurrences';

    protected $description = 'Delete error occurrences older than 60 days';

    public function handle(): int
    {
        $deleted = ErrorOccurrence::query()
            ->where('created_at', '<', now()->subDays(60))
            ->delete();

        $this->info("Pruned {$deleted} error occurrence(s) older than 60 days.");

        return self::SUCCESS;
    }
}
