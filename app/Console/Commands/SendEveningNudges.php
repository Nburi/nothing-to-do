<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Planning\EveningNudge;
use App\Services\PushNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Refactor step 3.3 (R2): sends tonight's nudge ("Zeit zum Abschalten", opens
 * /board?reset=1) to every user whose nudge time for today has come. All rules live in
 * App\Services\Planning\EveningNudge. Runs every minute (bootstrap/app.php); a missed tick
 * still sends on the next run within EveningNudge::CATCH_UP_MINUTES.
 */
class SendEveningNudges extends Command
{
    protected $signature = 'app:send-evening-nudges';

    protected $description = 'Send the evening reset nudge to users whose nudge time for today has come';

    public function handle(EveningNudge $nudge, PushNotifier $push): int
    {
        $now = CarbonImmutable::now();
        $sent = 0;

        User::query()
            ->whereNotNull('nudge_times')
            ->whereHas('pushSubscriptions')
            ->chunkById(100, function ($users) use ($nudge, $push, $now, &$sent) {
                foreach ($users as $user) {
                    // One user's trouble (a broken row, a push library error) never stops the others.
                    try {
                        $sent += (int) $nudge->sendIfDue($user, $push, $now);
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            });

        $this->info("Sent {$sent} evening nudge(s).");

        return self::SUCCESS;
    }
}
