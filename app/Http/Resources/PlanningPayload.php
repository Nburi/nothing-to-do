<?php

namespace App\Http\Resources;

use App\Planning\Crunch\AlertState;
use App\Planning\Crunch\Forecast;
use App\Planning\DayLoad;
use App\Planning\Session\SessionState;
use App\Services\Planning\Suggestion;
use Carbon\CarbonImmutable;

/**
 * JSON shapes of the refactor's planning engines, shared by the REST API and
 * the MCP tools (step 1.5, API parity). Instants are ISO 8601, dates Y-m-d.
 */
final class PlanningPayload
{
    public static function suggestion(Suggestion $s): array
    {
        return [
            'status' => $s->status->value,
            'battery' => $s->battery->value,
            'task' => $s->task === null ? null : (new TaskResource($s->task))->resolve(),
            'start_with' => $s->startWith,
            'why' => $s->why,
            'reason' => $s->pick?->reason->value,
            'minutes' => $s->pick?->minutes,
            'shrunk' => $s->pick?->shrunk ?? false,
            'deadline' => $s->pick?->deadline,
            'deadline_is_hard' => $s->pick?->deadlineIsHard ?? false,
            'start_by' => $s->pick?->startBy,
            'category_id' => $s->categoryId,
            'next_block_at' => $s->nextBlockAt?->toIso8601String(),
            'alternatives' => $s->alternatives,
        ];
    }

    public static function session(SessionState $state, int $remainingSeconds, CarbonImmutable $now): array
    {
        return [
            'phase' => $state->phase->value,
            'task_id' => $state->taskId,
            'paused' => $state->isPaused(),
            'remaining_seconds' => $remainingSeconds,
            'ends_at' => $state->phase->isTimed() && ! $state->isPaused()
                ? $now->addSeconds($remainingSeconds)->toIso8601String()
                : null,
            'sessions_today' => $state->sessionsToday,
            'done_task_ids' => $state->doneTaskIds,
        ];
    }

    public static function forecast(Forecast $forecast, AlertState $alert): array
    {
        return [
            'level' => $forecast->level()?->value,
            'crunch_date' => $forecast->crunchDate(),
            'missing_minutes' => $forecast->missingMinutes(),
            'slack_minutes' => $forecast->slackMinutes,
            'slip_first' => $forecast->slipFirst(),
            'late_task_ids' => [...$forecast->hardLateTaskIds, ...$forecast->softLateTaskIds],
            'warning' => [
                'active' => $alert->active,
                'level' => $alert->level?->value,
                'crunch_date' => $alert->crunchDate,
                'missing_minutes' => $alert->missingMinutes,
            ],
            'days' => array_map(fn (DayLoad $d) => [
                'date' => $d->date,
                'capacity_sessions' => $d->capacitySessions,
                'capacity_minutes' => $d->capacityMinutes,
                'load_minutes' => $d->loadMinutes,
                'overloaded' => $d->overloaded,
            ], $forecast->days),
        ];
    }
}
