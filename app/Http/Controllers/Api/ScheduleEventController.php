<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ScheduleEventResource;
use App\Models\ScheduleEvent;
use App\Services\PomodoroSessionService;
use App\Services\TaskSuggestor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ScheduleEventController extends Controller
{
    protected function userEvent(Request $request, int $id): ScheduleEvent
    {
        return $request->user()->scheduleEvents()->findOrFail($id);
    }

    /**
     * List events for a single date (default: the user's local today) or an
     * explicit date range. Recurring templates are materialised into concrete
     * rows for the requested window first, same as the Zeitplan page.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['sometimes', 'date'],
            'start' => ['sometimes', 'date'],
            'end' => ['sometimes', 'date', 'after_or_equal:start'],
        ]);

        $user = $request->user();

        if (isset($data['start']) || isset($data['end'])) {
            $start = Carbon::parse($data['start'] ?? $data['end']);
            $end = Carbon::parse($data['end'] ?? $data['start']);
        } elseif (isset($data['date'])) {
            $start = $end = Carbon::parse($data['date']);
        } else {
            $start = $end = $user->localToday();
        }

        ScheduleEvent::materializeRange($user, $start->copy(), $end->copy());

        $events = ScheduleEvent::forUser($user)
            ->visible()
            ->forRange($start, $end)
            ->ordered()
            ->with('category')
            ->get();

        return ScheduleEventResource::collection($events)->response();
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return (new ScheduleEventResource($this->userEvent($request, $id)->load('category')))->response();
    }

    public function store(Request $request): JsonResponse
    {
        $rules = [
            'kind' => ['required', 'in:appointment,category'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'recurring' => ['sometimes', 'boolean'],
            'days' => ['sometimes', 'array'],
            'days.*' => ['integer', 'between:1,7'],
            'save_as_template' => ['sometimes', 'boolean'],
        ];

        if ($request->input('kind') === 'category') {
            $rules['category_id'] = ['required', Rule::exists('event_categories', 'id')->where('user_id', $request->user()->id)];
        } else {
            $rules['title'] = ['required', 'string', 'max:255'];
            $rules['color'] = ['required', Rule::in(ScheduleEvent::EVENT_COLORS)];
        }

        $data = $request->validate($rules);
        $user = $request->user();

        if ($data['kind'] === 'category') {
            $category = $user->eventCategories()->findOrFail($data['category_id']);
            $categoryId = $category->id;
            $title = $category->name;
            $color = $category->color;
        } else {
            $categoryId = null;
            $title = trim($data['title']);
            $color = $data['color'];
        }

        $date = Carbon::parse($data['date']);
        $duration = ScheduleEvent::toMinutes($data['end_time']) - ScheduleEvent::toMinutes($data['start_time']);

        if ($data['recurring'] ?? false) {
            $days = $data['days'] ?? [];
            $days = $days !== [] ? $days : [$date->dayOfWeekIso];

            $template = $user->eventTemplates()->create([
                'category_id' => $categoryId,
                'name' => $title,
                'color' => $color,
                'duration' => $duration,
                'default_start' => $data['start_time'],
                'is_recurring' => true,
                'recurrence' => implode(',', $days),
            ]);

            ScheduleEvent::materializeRange($user, $date->copy()->startOfWeek(), $date->copy()->endOfWeek());

            if ($template->occursOn($date)) {
                ScheduleEvent::materializeRange($user, $date->copy()->startOfDay(), $date->copy()->startOfDay());
            }

            $event = ScheduleEvent::forUser($user)->where('template_id', $template->id)->forDay($date)->first();

            return (new ScheduleEventResource($event ?? $user->scheduleEvents()->latest()->first()))->response()->setStatusCode(201);
        }

        if ($data['kind'] === 'appointment' && ($data['save_as_template'] ?? false)) {
            $user->eventTemplates()->create([
                'name' => $title,
                'color' => $color,
                'duration' => $duration,
                'default_start' => $data['start_time'],
                'is_recurring' => false,
            ]);
        }

        $event = $user->scheduleEvents()->create([
            'category_id' => $categoryId,
            'title' => $title,
            'color' => $color,
            'date' => $data['date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
        ]);

        return (new ScheduleEventResource($event->fresh()))->response()->setStatusCode(201);
    }

    /**
     * Partial update — covers renaming/recolouring a Termin, switching a block
     * to/from a category, and drag-to-move / drag-to-resize (both are just a
     * start_time/end_time change). Moving a recurring occurrence to another
     * day detaches it into a one-off and tombstones the original slot, so
     * materialisation never regenerates a duplicate there.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $event = $this->userEvent($request, $id);
        $user = $request->user();

        $data = $request->validate([
            'category_id' => ['sometimes', 'nullable', Rule::exists('event_categories', 'id')->where('user_id', $user->id)],
            'title' => ['sometimes', 'string', 'max:255'],
            'color' => ['sometimes', Rule::in(ScheduleEvent::EVENT_COLORS)],
            'date' => ['sometimes', 'date'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i'],
        ]);

        $updates = [];

        if (array_key_exists('category_id', $data) && $data['category_id'] !== null) {
            $category = $user->eventCategories()->findOrFail($data['category_id']);
            $updates['category_id'] = $category->id;
            $updates['title'] = $category->name;
            $updates['color'] = $category->color;
        } else {
            if (array_key_exists('category_id', $data)) {
                $updates['category_id'] = null;
            }
            if (array_key_exists('title', $data)) {
                $updates['title'] = trim($data['title']);
            }
            if (array_key_exists('color', $data)) {
                $updates['color'] = $data['color'];
            }
        }

        if (array_key_exists('start_time', $data)) {
            $updates['start_time'] = $data['start_time'];
        }
        if (array_key_exists('end_time', $data)) {
            $updates['end_time'] = $data['end_time'];
        }
        if (isset($updates['start_time']) && isset($updates['end_time'])
            && ScheduleEvent::toMinutes($updates['end_time']) <= ScheduleEvent::toMinutes($updates['start_time'])) {
            return response()->json(['message' => 'end_time must be after start_time'], 422);
        }

        $origTemplate = $event->template_id;
        $origDate = $event->date->toDateString();
        $movedFromSeries = false;

        if (array_key_exists('date', $data)) {
            $updates['date'] = $data['date'];
            $movedFromSeries = $origTemplate !== null && $origDate !== $data['date'];
            if ($movedFromSeries) {
                $updates['template_id'] = null;
            }
        }

        $event->update($event->withNotifiedReset($updates));

        if ($movedFromSeries) {
            $user->scheduleEvents()->create([
                'template_id' => $origTemplate,
                'date' => $origDate,
                'start_time' => $data['start_time'] ?? $event->start_time,
                'end_time' => $data['end_time'] ?? $event->end_time,
                'is_cancelled' => true,
            ]);
        }

        return (new ScheduleEventResource($event->fresh('category')))->response();
    }

    /** Delete a one-off; cancel (tombstone) a recurring occurrence so it can't regenerate. */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $event = $this->userEvent($request, $id);

        if ($event->template_id !== null) {
            $event->update(['is_cancelled' => true]);
        } else {
            $event->delete();
        }

        return response()->json(null, 204);
    }

    /**
     * Start the Pomodoro focus timer on a category block. A tap before the
     * block's scheduled start begins the cycle now — reaching the scheduled
     * time never auto-starts it. Always manual, regardless of the autostart
     * setting (that only governs transitions after this first session).
     */
    public function startFocus(Request $request, int $id, PomodoroSessionService $pomodoro): JsonResponse
    {
        $event = $this->userEvent($request, $id);

        if (! $event->category?->pomodoro_enabled) {
            return response()->json(['message' => 'This block has no Pomodoro timer enabled.'], 422);
        }

        $pomodoro->start($event, $request->user());

        return (new ScheduleEventResource($event->fresh('category')))->response();
    }

    /** Fully ends the session — a fresh start-focus call is needed to begin again. */
    public function stopFocus(Request $request, int $id, PomodoroSessionService $pomodoro): JsonResponse
    {
        $event = $this->userEvent($request, $id);
        $pomodoro->stop($event);

        return (new ScheduleEventResource($event->fresh('category')))->response();
    }

    /**
     * Manually continue into the next queued phase — used when autostart is
     * disabled and the previous phase finished (frozen awaiting a continue),
     * or to skip a break early (see skipFocusBreak below for the dedicated
     * "skip a break I haven't reached yet" case).
     */
    public function continueFocus(Request $request, int $id, PomodoroSessionService $pomodoro): JsonResponse
    {
        $event = $this->userEvent($request, $id);

        if ($event->pomodoro_phase === null) {
            return response()->json(['message' => 'No Pomodoro session is running on this block.'], 422);
        }

        $pomodoro->transition($event, $request->user(), $request->user()->pomodoro());

        return (new ScheduleEventResource($event->fresh('category')))->response();
    }

    /**
     * Skip the current or upcoming break entirely and jump straight into the
     * next work session — works whether the break is actively running or
     * still frozen awaiting its own manual start.
     */
    public function skipFocusBreak(Request $request, int $id, PomodoroSessionService $pomodoro): JsonResponse
    {
        $event = $this->userEvent($request, $id);

        if ($event->pomodoro_phase === null) {
            return response()->json(['message' => 'No Pomodoro session is running on this block.'], 422);
        }

        if (! $pomodoro->skipBreak($event, $request->user(), $request->user()->pomodoro())) {
            return response()->json(['message' => 'Nothing to skip — the upcoming phase is not a break.'], 422);
        }

        return (new ScheduleEventResource($event->fresh('category')))->response();
    }

    /**
     * The header strip's "what's happening right now": the active/imminent
     * Pomodoro-enabled block (if any), its current phase, and a task
     * suggestion for the running focus cycle.
     */
    public function focus(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = $user->localToday();

        ScheduleEvent::materializeRange($user, $today, $today->copy());

        $events = ScheduleEvent::forUser($user)->visible()->forDay($today)->ordered()->with('category')->get();

        $now = $user->localNow();
        $nowMin = $now->hour * 60 + $now->minute;

        $session = $events->first(function (ScheduleEvent $e) use ($now, $nowMin) {
            if (! $e->category?->pomodoro_enabled) {
                return false;
            }
            if ($e->pomodoro_phase !== null) {
                return true;
            }
            $untilStart = $e->startMinutes() - $nowMin;

            return $e->isActive($now) || ($untilStart >= 0 && $untilStart <= 5);
        });

        if ($session === null) {
            return response()->json(['focus_session' => null, 'phase' => null, 'suggestion' => null]);
        }

        $phase = $session->pomodoroPhaseNow(now(), $user->pomodoro(), (bool) $user->pomodoro_autostart);
        $suggestion = null;

        if ($phase === null) {
            $suggestion = TaskSuggestor::suggest($user, 1, $session->id);
        } else {
            $effectivePhase = $phase['awaiting_next'] ? $phase['next_phase'] : $phase['phase'];
            $effectiveCycle = $phase['awaiting_next'] ? $phase['next_cycle'] : $phase['cycle'];

            if ($effectivePhase === 'work') {
                $suggestion = TaskSuggestor::suggest($user, $effectiveCycle, $session->id);
            }
        }

        return response()->json([
            'focus_session' => (new ScheduleEventResource($session))->toArray($request),
            'phase' => $phase,
            'suggestion' => $suggestion,
        ]);
    }
}
