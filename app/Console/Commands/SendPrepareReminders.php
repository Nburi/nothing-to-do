<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PushNotifier;
use Illuminate\Console\Command;

/**
 * Sends one push a day nudging the user to run their "Vorbereitung" ritual,
 * for anyone with prepare_reminder_mode 'automatic' or 'fixed'. Runs every
 * minute (see bootstrap/app.php). Dedup is via users.prepare_reminder_sent_on
 * (the user's local calendar date) — a due-check of "the reminder time has
 * passed" rather than an exact-minute match, so a delayed/missed tick still
 * fires on the next run instead of permanently losing the notification.
 *
 * "fixed" mode uses the user's own chosen time (prepare_reminder_time).
 * "automatic" mode has no chosen time — its due time (see
 * User::prepareReminderDueTime()) is only a fallback "last call" for when the
 * in-app banner (TaskBoard::showPreparePrompt) was never seen because the app
 * was never opened during the relevant half of the day.
 */
class SendPrepareReminders extends Command
{
    protected $signature = 'app:send-prepare-reminders';

    protected $description = 'Send a daily push reminder to run the Vorbereitung ritual, for users who opted in';

    public function handle(PushNotifier $pushNotifier): int
    {
        $sent = 0;

        User::query()
            ->whereIn('prepare_reminder_mode', ['automatic', 'fixed'])
            ->chunkById(50, function ($users) use ($pushNotifier, &$sent) {
                foreach ($users as $user) {
                    $due = $user->prepareReminderDueTime();

                    if ($due === null) {
                        continue;
                    }

                    $today = $user->localToday();

                    if ($user->localNow()->format('H:i') < $due) {
                        continue;
                    }

                    if ($user->hasPreparedToday()) {
                        continue;
                    }

                    if ($user->prepare_reminder_sent_on?->toDateString() === $today->toDateString()) {
                        continue;
                    }

                    $body = $user->prepare_time_of_day === 'morning'
                        ? 'Zeit, den Tag zu planen.'
                        : 'Zeit, dich für morgen vorzubereiten.';

                    $pushNotifier->notify($user, [
                        'title' => 'Vorbereitung',
                        'body' => $body,
                        'url' => '/app/prepare',
                    ]);

                    $user->update(['prepare_reminder_sent_on' => $today->toDateString()]);
                    $sent++;
                }
            });

        $this->info("Sent {$sent} Vorbereitung reminder(s).");

        return self::SUCCESS;
    }
}
