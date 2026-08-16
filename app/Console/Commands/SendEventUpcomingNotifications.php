<?php

namespace App\Console\Commands;

use App\Models\ScheduleEvent;
use App\Models\User;
use App\Services\PushNotifier;
use Illuminate\Console\Command;

/**
 * Sends a push 5 minutes before a today's schedule event starts, for every
 * user who opted in (notify_event_upcoming) — independent of, and in
 * addition to, notify_event_start. Runs every minute (see
 * bootstrap/app.php). Dedup is via schedule_events.notified_upcoming_at, a
 * separate column from notified_at, since the two notifications are
 * independent opt-ins and one event can fire both.
 */
class SendEventUpcomingNotifications extends Command
{
    protected $signature = 'app:send-event-upcoming-notifications';

    protected $description = 'Send a push notification 5 minutes before every schedule event starts, for users who opted in';

    private const LEAD_MINUTES = 5;

    public function handle(PushNotifier $pushNotifier): int
    {
        $sent = 0;

        User::query()->where('notify_event_upcoming', true)->chunkById(50, function ($users) use ($pushNotifier, &$sent) {
            foreach ($users as $user) {
                $today = $user->localToday();

                ScheduleEvent::materializeRange($user, $today, $today->copy());

                $now = now();

                // <= today, not forDay($today): a cron outage spanning the user's local
                // midnight must not permanently strand a still-due reminder on a day
                // that's already rolled over — the threshold check below still
                // correctly excludes anything not yet due.
                ScheduleEvent::forUser($user)
                    ->visible()
                    ->whereDate('date', '<=', $today->toDateString())
                    ->whereNull('notified_upcoming_at')
                    ->get()
                    ->each(function (ScheduleEvent $event) use ($user, $now, $pushNotifier, &$sent) {
                        $threshold = $event->startInstantUtc($user)->subMinutes(self::LEAD_MINUTES);

                        if ($threshold->greaterThan($now)) {
                            return;
                        }

                        $pushNotifier->notify($user, [
                            'title' => 'Zeitplan',
                            'body' => "{$event->displayTitle()} beginnt in ".self::LEAD_MINUTES.' Minuten',
                            'url' => '/app/schedule',
                        ]);

                        $event->update(['notified_upcoming_at' => $now]);
                        $sent++;
                    });
            }
        });

        $this->info("Sent {$sent} event-upcoming notification(s).");

        return self::SUCCESS;
    }
}
