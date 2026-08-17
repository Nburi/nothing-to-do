<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Services\ProgressStats;
use App\Services\PushNotifier;
use Illuminate\Console\Command;

/**
 * Two independent daily nudges, each opted into and dated separately:
 *
 *  - "Offene Aufgaben am Abend" (notify_daily_reminder / daily_reminder_time):
 *    once today's chosen time has passed, if today still has open
 *    "Heute"-flagged board tasks. Dedup: daily_reminder_sent_on.
 *  - "Serie in Gefahr" (notify_streak_risk, fixed User::STREAK_RISK_DUE_TIME):
 *    once that fixed time has passed, if the user has a real trailing streak
 *    (ProgressStats::currentStreak() counts through yesterday while today is
 *    still empty — see its docblock) but hasn't completed anything yet
 *    today. Dedup: streak_risk_sent_on.
 *
 * Runs every minute (see bootstrap/app.php). Both dedup columns are "already
 * sent today", not an exact-minute match, so a delayed/missed cron tick still
 * fires on the next run instead of losing the notification — same pattern as
 * every other reminder command in this app (see SendPrepareReminders).
 */
class SendProgressReminders extends Command
{
    protected $signature = 'app:send-progress-reminders';

    protected $description = 'Send the open-tasks-tonight and streak-at-risk push reminders, for users who opted in';

    public function handle(PushNotifier $pushNotifier): int
    {
        $sent = 0;

        User::query()
            ->where('notify_daily_reminder', true)
            ->orWhere('notify_streak_risk', true)
            ->chunkById(50, function ($users) use ($pushNotifier, &$sent) {
                foreach ($users as $user) {
                    $sent += (int) $this->maybeSendDailyReminder($user, $pushNotifier);
                    $sent += (int) $this->maybeSendStreakRisk($user, $pushNotifier);
                }
            });

        $this->info("Sent {$sent} progress reminder(s).");

        return self::SUCCESS;
    }

    private function maybeSendDailyReminder(User $user, PushNotifier $pushNotifier): bool
    {
        if (! $user->notify_daily_reminder) {
            return false;
        }

        $today = $user->localToday();

        if ($user->daily_reminder_sent_on?->toDateString() === $today->toDateString()) {
            return false;
        }

        if ($user->localNow()->format('H:i') < ($user->daily_reminder_time ?? '19:00')) {
            return false;
        }

        // Same filter as TaskBoard::today() — active board tasks flagged for today.
        $open = Task::query()->forUser($user)->active()->onBoard()->where('is_today', true)->count();

        if ($open === 0) {
            return false;
        }

        $pushNotifier->notify($user, [
            'title' => 'Offene Aufgaben',
            'body' => $open === 1
                ? 'Du hast noch 1 Aufgabe für heute offen.'
                : "Du hast noch {$open} Aufgaben für heute offen.",
            'url' => '/app',
        ]);

        $user->update(['daily_reminder_sent_on' => $today->toDateString()]);

        return true;
    }

    private function maybeSendStreakRisk(User $user, PushNotifier $pushNotifier): bool
    {
        if (! $user->notify_streak_risk) {
            return false;
        }

        $today = $user->localToday();

        if ($user->streak_risk_sent_on?->toDateString() === $today->toDateString()) {
            return false;
        }

        if ($user->localNow()->format('H:i') < User::STREAK_RISK_DUE_TIME) {
            return false;
        }

        $counts = ProgressStats::completedCountsByDay($user);

        if (ProgressStats::todayCount($user, $counts) > 0) {
            return false; // already safe for today
        }

        $streak = ProgressStats::currentStreak($user, $counts);

        if ($streak === 0) {
            return false; // nothing trailing to protect
        }

        $pushNotifier->notify($user, [
            'title' => 'Serie in Gefahr',
            'body' => $streak === 1
                ? 'Deine Serie reisst, wenn du heute nichts erledigst.'
                : "Deine Serie von {$streak} Tagen reisst, wenn du heute nichts erledigst.",
            'url' => '/app',
        ]);

        $user->update(['streak_risk_sent_on' => $today->toDateString()]);

        return true;
    }
}
