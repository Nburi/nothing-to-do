<?php

namespace App\Console\Commands;

use App\Models\ScheduleEvent;
use App\Models\User;
use App\Services\PushNotifier;
use Illuminate\Console\Command;

/**
 * Sends a push the moment a today's schedule event starts, for every user
 * who opted in (notify_event_start). Runs every minute (see
 * bootstrap/app.php). Dedup is via schedule_events.notified_at — a due-check
 * of "start instant already passed" rather than a sliding time window, so a
 * delayed/missed tick still fires on the next run instead of permanently
 * losing the notification.
 */
class SendEventStartNotifications extends Command
{
    protected $signature = 'app:send-event-start-notifications';

    protected $description = 'Send a push notification for every schedule event starting now, for users who opted in';

    public function handle(PushNotifier $pushNotifier): int
    {
        $sent = 0;

        User::query()->where('notify_event_start', true)->chunkById(50, function ($users) use ($pushNotifier, &$sent) {
            foreach ($users as $user) {
                $today = $user->localToday();

                ScheduleEvent::materializeRange($user, $today, $today->copy());

                $now = now();

                // <= today, not forDay($today): a cron outage spanning the user's local
                // midnight must not permanently strand a still-due event on a day that's
                // already rolled over — the startInstantUtc() check below still correctly
                // excludes anything not yet due.
                ScheduleEvent::forUser($user)
                    ->visible()
                    ->whereDate('date', '<=', $today->toDateString())
                    ->whereNull('notified_at')
                    ->get()
                    ->each(function (ScheduleEvent $event) use ($user, $now, $pushNotifier, &$sent) {
                        if ($event->startInstantUtc($user)->greaterThan($now)) {
                            return;
                        }

                        $pushNotifier->notify($user, [
                            'title' => 'Zeitplan',
                            'body' => "{$event->displayTitle()} beginnt jetzt",
                            'url' => '/app/schedule',
                        ]);

                        $event->update(['notified_at' => $now]);
                        $sent++;
                    });
            }
        });

        $this->info("Sent {$sent} event-start notification(s).");

        return self::SUCCESS;
    }
}
