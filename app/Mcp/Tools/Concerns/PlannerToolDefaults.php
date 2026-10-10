<?php

namespace App\Mcp\Tools\Concerns;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Models\AgendaEntry;
use App\Models\User;
use App\Services\DayPlanner;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Shared by every Planer tool. The Planer is an opt-in feature with its own
 * switch (users.planner_enabled, Settings → Planer) instead of an AppModules
 * entry, so the tools follow that switch: off = the tools do not exist.
 */
trait PlannerToolDefaults
{
    public function isEnabledFor(User $user): bool
    {
        return (bool) $user->planner_enabled;
    }

    public function requiredFeatureLabel(): ?string
    {
        return 'Planer';
    }

    /** A Y-m-d date inside the board's horizon (today … today + HORIZON_DAYS − 1); anything else is refused, as the client-trusting Livewire action never had to. */
    protected static function horizonDate(User $user, mixed $value, string $field = 'date'): string
    {
        $date = is_string($value) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        $errors = \DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw ValidationException::withMessages([$field => "{$field} must be a real date (YYYY-MM-DD)."]);
        }

        $day = Carbon::instance($date)->startOfDay();
        $today = $user->localToday();

        if ($day->lt($today) || $day->gt($today->copy()->addDays(DayPlanner::HORIZON_DAYS - 1))) {
            throw ValidationException::withMessages([
                $field => "{$field} must lie between today and ".$today->copy()->addDays(DayPlanner::HORIZON_DAYS - 1)->toDateString().' (the Planer shows '.DayPlanner::HORIZON_DAYS.' days).',
            ]);
        }

        return $day->toDateString();
    }

    /**
     * "task:<id>" / "agenda:<id>" for one {task_id | agenda_entry_id} reference, after checking the
     * caller may use it. A planned task must be an active one of this user's; an Agenda reference must be
     * open homework they can see (it is promoted into a real task when placed).
     *
     * @param  array<string, mixed>  $ref
     */
    protected static function plannerToken(User $user, array $ref): string
    {
        $taskId = $ref['task_id'] ?? null;
        $entryId = $ref['agenda_entry_id'] ?? null;

        if (($taskId === null) === ($entryId === null)) {
            throw new McpToolExecutionException('Give exactly one of task_id or agenda_entry_id.');
        }

        if ($taskId !== null) {
            $task = $user->tasks()->active()->find((int) $taskId);

            if ($task === null) {
                throw new McpToolExecutionException("No open task with id {$taskId} found for this account.");
            }

            return 'task:'.$task->id;
        }

        $entry = AgendaEntry::visibleTo($user)->ofType('homework')->openFor($user)->find((int) $entryId);

        if ($entry === null) {
            throw new McpToolExecutionException("No open homework entry with id {$entryId} found for this account.");
        }

        return 'agenda:'.$entry->id;
    }

    /**
     * A planner item (DayPlanner's camelCase array) in the snake_case shape the other tools use.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    protected static function presentItem(array $item): array
    {
        return array_filter([
            'type' => $item['type'],
            'id' => $item['id'],
            'title' => $item['title'],
            'list' => $item['list'] ?? null,
            'subject' => $item['subject'] ?? null,
            'duration_minutes' => $item['duration'],
            'has_estimate' => $item['hasEstimate'] ?? null,
            'days_until_deadline' => $item['deadlineOffset'],
            'deadline_label' => $item['deadlineLabel'] ?? null,
            'is_important' => $item['isImportant'],
            'planned_date' => $item['plannedDate'] ?? null,
            'planned_date_label' => $item['plannedDateLabel'] ?? null,
        ], fn ($v) => $v !== null);
    }
}
