<?php

namespace App\Mcp\Tools;

use App\Mcp\McpTool;
use App\Models\User;
use App\Services\AppModules;
use App\Services\HeaderBadges;
use App\Services\ListConcepts;

class GetSettingsTool extends McpTool
{
    public function name(): string
    {
        return 'get_settings';
    }

    public function description(): string
    {
        return 'This user\'s app-level settings: active list concept, which optional feature modules are '
            .'visible, the default landing page, and (only when the relevant module is visible) the daily '
            .'task goal and Pomodoro rhythm.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) []];
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $settings = [
            'list_concept' => ListConcepts::for($user),
            'available_list_concepts' => array_values(array_filter(
                array_map(fn ($row) => $row['available'] ? $row['key'] : null, ListConcepts::rowsFor($user)),
            )),
            'visible_modules' => array_values(array_filter(
                array_map(fn ($row) => $row['hidden'] ? null : $row['key'], AppModules::rowsFor($user)),
            )),
            'hidden_modules' => AppModules::hiddenKeys($user),
            'default_page' => $user->default_page,
        ];

        $settings += [
            'planner_enabled' => (bool) $user->planner_enabled,
            'timezone_offset' => $user->timezoneOffsetHours(),
            'timezone_auto_dst' => (bool) $user->timezone_auto_dst,
            'task_reset_time' => $user->task_reset_time ?? '01:00',
            'day_frame' => ['start' => $user->day_start_time ?? '06:00', 'end' => $user->day_end_time ?? '23:00'],
            'prepare' => [
                'time_of_day' => $user->prepare_time_of_day,
                'reminder_mode' => $user->prepare_reminder_mode ?? 'off',
                'reminder_time' => $user->prepare_reminder_time,
            ],
            'notifications' => [
                'event_start' => (bool) $user->notify_event_start,
                'event_upcoming' => (bool) $user->notify_event_upcoming,
                'pomodoro_start' => (bool) $user->notify_pomo_start,
                'break_start' => (bool) $user->notify_break_start,
                'daily_reminder' => (bool) $user->notify_daily_reminder,
                'daily_reminder_time' => $user->daily_reminder_time ?? '19:00',
                'streak_risk' => (bool) $user->notify_streak_risk,
                'day_preview' => (bool) $user->notify_day_preview,
                'day_preview_time' => $user->day_preview_notification_time ?? '07:30',
            ],
            'deadline_preview_enabled' => (bool) $user->deadline_preview_enabled,
            'deadline_preview_days' => (int) $user->deadline_preview_days,
            'homework_preview_enabled' => (bool) $user->homework_preview_enabled,
            'header_badges' => HeaderBadges::preferenceRowsFor($user),
        ];

        if ($user->agendaSpaces()->exists()) {
            $settings['show_presence'] = (bool) $user->show_presence;
        }

        if (AppModules::isVisible($user, 'progress')) {
            $settings['daily_task_goal'] = $user->dailyTaskGoal();
        }

        if (AppModules::isVisible($user, 'schedule')) {
            $settings['pomodoro_rhythm'] = $user->pomodoro();
            $settings['pomodoro_autostart'] = (bool) $user->pomodoro_autostart;
        }

        return $settings;
    }
}
