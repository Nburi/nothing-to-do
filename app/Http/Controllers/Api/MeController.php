<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    /** Account info, rhythm/timezone settings, and board counts — one call to orient a Shortcut. */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'name' => $user->name,
            'email' => $user->email,
            'task_reset_time' => $user->task_reset_time ?? '01:00',
            'pomodoro' => $user->pomodoro(),
            'pomodoro_autostart' => (bool) $user->pomodoro_autostart,
            'notify_event_start' => (bool) $user->notify_event_start,
            'notify_pomo_start' => (bool) $user->notify_pomo_start,
            'notify_break_start' => (bool) $user->notify_break_start,
            'timezone_offset' => $user->timezoneOffsetHours(),
            'timezone_auto_dst' => (bool) $user->timezone_auto_dst,
            'local_now' => $user->localNow()->toIso8601String(),
            'counts' => [
                'inbox' => Task::forUser($user)->onBoard()->inList('inbox')->active()->count(),
                'todos' => Task::forUser($user)->onBoard()->inList('todos')->active()->count(),
                'tasks' => Task::forUser($user)->onBoard()->inList('tasks')->active()->count(),
                'today' => Task::forUser($user)->onBoard()->active()->where('is_today', true)->count(),
                'projects' => Project::forUser($user)->count(),
            ],
        ]);
    }

    /** Update account-level settings: daily reset time, Pomodoro rhythm/autostart, notifications, timezone. */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'task_reset_time' => ['sometimes', 'date_format:H:i'],
            'pomodoro_work' => ['sometimes', 'integer', 'min:1'],
            'pomodoro_short_break' => ['sometimes', 'integer', 'min:1'],
            'pomodoro_long_break' => ['sometimes', 'integer', 'min:1'],
            'pomodoro_long_every' => ['sometimes', 'integer', 'min:1'],
            'pomodoro_autostart' => ['sometimes', 'boolean'],
            'notify_event_start' => ['sometimes', 'boolean'],
            'notify_pomo_start' => ['sometimes', 'boolean'],
            'notify_break_start' => ['sometimes', 'boolean'],
            'timezone_offset' => ['sometimes', 'numeric', 'between:-12,14'],
            'timezone_auto_dst' => ['sometimes', 'boolean'],
        ]);

        $user->update($data);

        return $this->show($request);
    }
}
