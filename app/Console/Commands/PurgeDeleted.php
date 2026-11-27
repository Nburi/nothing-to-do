<?php

namespace App\Console\Commands;

use App\Models\Task;
use Illuminate\Console\Command;

/**
 * Deleted tasks stay in the database for 30 days so "Rückgängig" can take them back with every day
 * plan and work session intact (step 4.9); after that this command removes them for good (Niels,
 * 2026-11-26). Runs daily in the scheduler.
 *
 * Only rows that were soft-deleted MORE than 30 days ago are touched; a task deleted exactly 30 days
 * ago is still kept for the rest of that moment. Open and finished tasks are never matched (they
 * have no `deleted_at`). Projects and groups are not soft-deleted (ProjectEditor::deleteProject()
 * removes them at once and keeps their steps as plain tasks), and soft-deleted categories are a
 * separate decision (see TODO.md), so nothing else is purged here.
 */
class PurgeDeleted extends Command
{
    /** How long a deleted task can still be taken back. */
    public const KEEP_DAYS = 30;

    protected $signature = 'app:purge-deleted {--dry-run : Only count what would be removed}';

    protected $description = 'Permanently remove tasks that were deleted more than 30 days ago';

    public function handle(): int
    {
        $cutoff = now()->subDays(self::KEEP_DAYS);
        $query = Task::onlyTrashed()->where('deleted_at', '<', $cutoff);

        if ($this->option('dry-run')) {
            $this->info("Would purge {$query->count()} task(s) deleted before {$cutoff->toDateTimeString()}.");

            return self::SUCCESS;
        }

        $purged = 0;
        // One by one so the database cascades (day plans, work sessions, links) run per task.
        $query->clone()->chunkById(200, function ($tasks) use (&$purged) {
            foreach ($tasks as $task) {
                $task->forceDelete();
                $purged++;
            }
        });

        $this->info("Purged {$purged} task(s) deleted more than ".self::KEEP_DAYS.' days ago.');

        return self::SUCCESS;
    }
}
