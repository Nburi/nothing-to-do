<?php

namespace App\Console\Commands;

use App\Models\TaskDayPlan;
use App\Models\User;
use App\Services\DayPlanner;
use Illuminate\Console\Command;

/**
 * The passive half of "planned in the Planer, becomes Heute when the day
 * begins" (see App\Services\DayPlanner::promoteIfToday() for the immediate,
 * drag/autofill-straight-onto-today half, which this command's per-user
 * loop delegates to for the actual write). Runs every minute (see
 * bootstrap/app.php) and, per Planer user, gathers every day-plan whose
 * planned_date is today-or-earlier and hands the task ids to
 * DayPlanner::promoteIfToday(), which does the real is_today=false /
 * Inbox/Project filtering and write.
 *
 * `<=`, not `=` — the same "a missed tick catches up on the next run" shape
 * every other command in this app uses (see SendProgressReminders), except
 * here there is no separate dedup column at all: promoteIfToday()'s own
 * is_today=false filter is already the idempotency guard.
 */
class PromoteDayPlansToToday extends Command
{
    protected $signature = 'app:promote-day-plans-to-today';

    protected $description = 'Flag every task whose Planer day-plan has arrived as "Heute", for users who use the Planer';

    public function handle(): int
    {
        $checked = 0;

        User::query()
            ->where('planner_enabled', true)
            ->chunkById(50, function ($users) use (&$checked) {
                foreach ($users as $user) {
                    $today = $user->localToday()->toDateString();

                    $taskIds = TaskDayPlan::query()
                        ->whereDate('planned_date', '<=', $today)
                        ->whereHas('task', fn ($q) => $q->forUser($user))
                        ->pluck('task_id');

                    if ($taskIds->isEmpty()) {
                        continue;
                    }

                    DayPlanner::promoteIfToday($user, $taskIds, $today);
                    $checked++;
                }
            });

        $this->info("Checked {$checked} Planer user(s) for day-plans that arrived.");

        return self::SUCCESS;
    }
}
