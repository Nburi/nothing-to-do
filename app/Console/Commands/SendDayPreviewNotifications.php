<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DayPreviewData;
use App\Services\PushNotifier;
use Illuminate\Console\Command;

/**
 * Sends one push a day — "Dein Tag ist bereit" (or whichever title got
 * picked, see below) — once the user's own users.day_preview_notification_time
 * has passed locally, for anyone with notify_day_preview on. Runs every
 * minute (see bootstrap/app.php). Dedup is users.day_preview_notification_sent_on
 * (the user's local calendar date) — a due-check, not an exact-minute match,
 * so a delayed or missed cron tick still fires on the next run instead of
 * losing it, same shape every other scheduled reminder in this app uses.
 *
 * The trigger time is per-user (Settings' own "Dein Tag ist bereit" field) —
 * moved out of config/day_preview.php on request, so everyone can set their
 * own instead of sharing one app-wide time. The title is still picked at
 * random per push from config('day_preview.notification.titles') (editable
 * without touching code); the body is always DayPreviewData::
 * notificationSummary() — a live read, never a static string.
 */
class SendDayPreviewNotifications extends Command
{
    protected $signature = 'app:send-day-preview-notifications';

    protected $description = 'Send a daily "Dein Tag ist bereit" push summarising the day, for users who opted in';

    public function handle(PushNotifier $pushNotifier): int
    {
        $sent = 0;

        User::query()
            ->where('notify_day_preview', true)
            ->chunkById(50, function ($users) use ($pushNotifier, &$sent) {
                foreach ($users as $user) {
                    $today = $user->localToday();
                    $dueTime = $user->day_preview_notification_time ?? '07:30';

                    if ($user->localNow()->format('H:i') < $dueTime) {
                        continue;
                    }

                    if ($user->day_preview_notification_sent_on?->toDateString() === $today->toDateString()) {
                        continue;
                    }

                    $pushNotifier->notify($user, [
                        'title' => $this->pickTitle(),
                        'body' => DayPreviewData::notificationSummary($user),
                        'url' => '/app/today',
                    ]);

                    $user->update(['day_preview_notification_sent_on' => $today->toDateString()]);
                    $sent++;
                }
            });

        $this->info("Sent {$sent} Tagesüberblick notification(s).");

        return self::SUCCESS;
    }

    /** One title, picked at random per push — falls back to a plain default if the config list is ever emptied out. */
    private function pickTitle(): string
    {
        $titles = config('day_preview.notification.titles', []);

        return empty($titles) ? 'Dein Tag ist bereit' : $titles[array_rand($titles)];
    }
}
