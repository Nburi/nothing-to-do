<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Services\Planning\EveningNudge;
use App\Support\Locales;
use Illuminate\Validation\Rule;
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
            'notify_event_upcoming' => (bool) $user->notify_event_upcoming,
            'timezone_offset' => $user->timezoneOffsetHours(),
            'timezone_auto_dst' => (bool) $user->timezone_auto_dst,
            'locale' => Locales::for($user),
            // The evening nudge ("Abschalten") per ISO weekday, 1 = Monday; null = no nudge that day.
            'nudge_times' => self::nudgeTimes($user),
            'local_now' => $user->localNow()->toIso8601String(),
            'counts' => [
                'inbox' => Task::forUser($user)->inList('inbox')->active()->count(),
                'todos' => Task::forUser($user)->inList('todos')->active()->count(),
                'tasks' => Task::forUser($user)->inList('tasks')->active()->count(),
                'today' => Task::forUser($user)->active()->where('is_today', true)->count(),
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
            'notify_event_upcoming' => ['sometimes', 'boolean'],
            'timezone_offset' => ['sometimes', 'numeric', 'between:-12,14'],
            'timezone_auto_dst' => ['sometimes', 'boolean'],
            'locale' => ['sometimes', Rule::in(Locales::SUPPORTED)],
            'nudge_times' => ['sometimes', 'array'],
            'nudge_times.*' => ['nullable', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
        ]);

        $nudge = $data['nudge_times'] ?? null;
        unset($data['nudge_times']);
        $user->update($data);

        // Only the weekdays you pass change; null (or "") switches one off. Days 1-7, Monday first.
        if ($nudge !== null) {
            app(EveningNudge::class)->save($user, array_replace(EveningNudge::times($user), array_intersect_key($nudge, array_flip(EveningNudge::WEEKDAYS))));
        }

        return $this->show($request);
    }

    /** @return array<int, ?string> every ISO weekday, null where there is no nudge */
    private static function nudgeTimes(\App\Models\User $user): array
    {
        $saved = EveningNudge::times($user);

        return collect(EveningNudge::WEEKDAYS)->mapWithKeys(fn (int $day) => [$day => $saved[$day] ?? null])->all();
    }
}
