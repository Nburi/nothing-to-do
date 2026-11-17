<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ProgressStats;
use Illuminate\Console\Command;

/**
 * Retrospectively decides each user's PAST local days' streak outcomes
 * (perfect / frozen / a genuine break) — see ProgressStats::evaluatePastDay()
 * for the actual rules. This can only run after a day is truly over: whether
 * a day earns a "freeze" depends on it staying empty and under-goal for its
 * whole duration, which nothing can know while the day is still "today".
 *
 * Runs every minute (see bootstrap/app.php), same pattern as every other
 * reminder-shaped command here. Walks from a user's own
 * streak_last_evaluated_date (exclusive) through their own yesterday — never
 * further back than "yesterday" on a first-ever run, deliberately no deep
 * backfill into existing history (same "clean count from ship day" precedent
 * as tasks.today_date itself).
 */
class EvaluateStreakDays extends Command
{
    protected $signature = 'app:evaluate-streak-days';

    protected $description = "Decide each user's past local days as perfect, frozen, or broken for streak purposes";

    public function handle(): int
    {
        $evaluated = 0;

        User::query()->chunkById(50, function ($users) use (&$evaluated) {
            foreach ($users as $user) {
                $yesterday = $user->localToday()->subDay();

                $cursor = $user->streak_last_evaluated_date
                    ? $user->streak_last_evaluated_date->copy()->addDay()
                    : $yesterday->copy();

                if ($cursor->greaterThan($yesterday)) {
                    continue; // this user's "yesterday" hasn't moved since the last tick
                }

                while ($cursor->lessThanOrEqualTo($yesterday)) {
                    ProgressStats::evaluatePastDay($user, $cursor);
                    $evaluated++;
                    $cursor = $cursor->addDay();
                }

                $user->update(['streak_last_evaluated_date' => $yesterday->toDateString()]);
            }
        });

        $this->info("Evaluated {$evaluated} past streak day(s).");

        return self::SUCCESS;
    }
}
