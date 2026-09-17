<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\DayPreviewData;
use App\Services\PushNotifier;
use Illuminate\Console\Command;

/**
 * Sends one push a day — "Dein Tag ist bereit" — once config('day_preview.
 * notification.time') has passed in the user's own local time, for anyone
 * with notify_day_preview on. Runs every minute (see bootstrap/app.php).
 * Dedup is users.day_preview_notification_sent_on (the user's local
 * calendar date) — a due-check, not an exact-minute match, so a delayed or
 * missed cron tick still fires on the next run instead of losing it, same
 * shape every other scheduled reminder in this app already uses.
 *
 * The trigger time and title live in config/day_preview.php, editable
 * without touching code; the body is always DayPreviewData::
 * notificationSummary() — a live read, never a static string.
 */
class SendDayPreviewNotifications extends Command
{
    protected $signature = 'app:send-day-preview-notifications';

    protected $description = 'Send a daily "Dein Tag ist bereit" push summarising the day, for users who opted in';

    public function handle(PushNotifier $pushNotifier): int
    {
        $sent = 0;
        $dueTime = config('day_preview.notification.time', '07:30');
        $title = config('day_preview.notification.title', 'Dein Tag ist bereit');

        User::query()
            ->where('notify_day_preview', true)
            ->chunkById(50, function ($users) use ($pushNotifier, &$sent, $dueTime, $title) {
                foreach ($users as $user) {
                    $today = $user->localToday();

                    if ($user->localNow()->format('H:i') < $dueTime) {
                        continue;
                    }

                    if ($user->day_preview_notification_sent_on?->toDateString() === $today->toDateString()) {
                        continue;
                    }

                    $pushNotifier->notify($user, [
                        'title' => $title,
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
}
