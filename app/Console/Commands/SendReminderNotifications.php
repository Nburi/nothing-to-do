<?php

namespace App\Console\Commands;

use App\Models\Reminder;
use App\Models\User;
use App\Services\PushNotifier;
use App\Services\Reminders;
use Illuminate\Console\Command;

/**
 * Pushes "Nicht vergessen" reminders — the evening before and on the day itself
 * (see App\Services\Reminders for the timing rules: the evening time and the
 * "smart" morning time that follows the day's first block). Runs every minute
 * (bootstrap/app.php). Dedup is the reminders' own notified_before_at /
 * notified_on_at stamps, so a delayed or missed tick still fires on the next
 * run and a restart never sends one twice. A push whose moment passed before
 * the reminder was created is only stamped, never sent.
 *
 * Also tidies up: reminders whose day lies more than PRUNE_AFTER_DAYS behind
 * are deleted — a reminder has no history worth keeping.
 */
class SendReminderNotifications extends Command
{
    protected $signature = 'app:send-reminder-notifications';

    protected $description = 'Push "Nicht vergessen" reminders the evening before and on the day';

    public function handle(PushNotifier $pushNotifier): int
    {
        $sent = 0;

        // Reminder days are local dates, a user's "today" can differ from the server's by up to a day —
        // so pre-select with a window one day wider on each side and let the service decide per user.
        $from = now()->subDay()->toDateString();
        $to = now()->addDays(2)->toDateString();

        User::query()
            ->whereHas('reminders', fn ($q) => $q->whereNull('acknowledged_at')->whereDate('remind_on', '>=', $from)->whereDate('remind_on', '<=', $to))
            ->chunkById(50, function ($users) use ($pushNotifier, &$sent) {
                foreach ($users as $user) {
                    foreach (Reminders::dueNotifications($user) as $due) {
                        /** @var Reminder $reminder */
                        $reminder = $due['reminder'];

                        if (! $due['silent']) {
                            $pushNotifier->notify($user, Reminders::pushPayload($reminder, $due['kind']));
                            $sent++;
                        }

                        $reminder->update([$due['kind'] === 'before' ? 'notified_before_at' : 'notified_on_at' => now()]);
                    }
                }
            });

        $pruned = Reminder::query()->whereDate('remind_on', '<', now()->subDays(Reminders::PRUNE_AFTER_DAYS + 1)->toDateString())->delete();

        $this->info("Sent {$sent} reminder notification(s), pruned {$pruned} old reminder(s).");

        return self::SUCCESS;
    }
}
