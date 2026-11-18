<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Services\Planning\CrunchWatch;
use App\Services\Planning\SoftSlips;
use Illuminate\Console\Command;
use Throwable;

/**
 * Refactor step 1.4: the planning upkeep that has to happen without the app
 * being open. Hourly (bootstrap/app.php), idempotent, per user:
 *
 * 1. the soft-slip rule (G2) for soft deadlines that passed,
 * 2. the early warning's state (hysteresis, one warning a day).
 *
 * Only users with open work, or with a warning that is still on, are checked. One user's error never stops the
 * others. Sending the warning is step 4.2; until then `notify` is counted only.
 */
class RunPlanningChecks extends Command
{
    protected $signature = 'app:run-planning-checks';

    protected $description = 'Apply the soft-slip rule and update the early warning for every user with open work';

    public function handle(SoftSlips $slips, CrunchWatch $watch): int
    {
        $totals = ['users' => 0, 'rolled' => 0, 'later' => 0, 'dropped' => 0, 'warnings' => 0, 'failed' => 0];

        User::query()
            // Users with open work, and users whose warning is still on (their work may be gone, the warning must clear).
            ->where(fn ($q) => $q->whereHas('tasks', fn ($t) => $t->active()->whereIn('list', Task::WORK_LISTS))
                ->orWhereHas('crunchAlert', fn ($a) => $a->where('active', true)))
            ->chunkById(50, function ($users) use ($slips, $watch, &$totals) {
                foreach ($users as $user) {
                    try {
                        $counts = $slips->apply($user);
                        $decision = $watch->check($user);
                    } catch (Throwable $e) {
                        report($e);
                        $totals['failed']++;

                        continue;
                    }

                    $totals['users']++;
                    $totals['rolled'] += $counts['rolled'];
                    $totals['later'] += $counts['later'];
                    $totals['dropped'] += $counts['dropped'];
                    $totals['warnings'] += $decision->notify ? 1 : 0;
                }
            });

        $this->info(sprintf(
            'Checked %d user(s): %d soft date(s) rolled over, %d to Later, %d soft date(s) dropped, %d new warning(s), %d failed.',
            $totals['users'], $totals['rolled'], $totals['later'], $totals['dropped'], $totals['warnings'], $totals['failed'],
        ));

        return $totals['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
