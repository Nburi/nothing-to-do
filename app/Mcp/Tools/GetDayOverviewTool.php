<?php

namespace App\Mcp\Tools;

use App\Http\Resources\TaskResource;
use App\Mcp\McpTool;
use App\Models\AgendaEntry;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\User;
use App\Services\AppModules;
use App\Services\DayPlanner;
use App\Services\DayPreviewData;

/**
 * The "Tagesüberblick" page as one read: unlike DayPreviewData's tile reads it is NOT capped
 * to three rows per tile — a model answering "what does my day look like" needs all of it.
 * Module-aware exactly like the page (a hidden module contributes nothing).
 */
class GetDayOverviewTool extends McpTool
{
    public function name(): string
    {
        return 'get_day_overview';
    }

    public function description(): string
    {
        return 'Everything about today in one call: the Zeitplan blocks, tasks flagged for today, what is overdue / '
            .'due today / due within the next days (tasks and Agenda entries), upcoming homework, the Planer\'s plan for '
            .'today (when the Planer is on), the active Notfallmodus project, and progress towards the daily goal. '
            .'Hidden modules are left out. Start here for "what\'s on today?".';
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
        $today = $user->localToday();
        $soonCutoff = $today->copy()->addDays(Task::URGENCY_DAYS);

        $overview = [
            'date' => $today->toDateString(),
            'weekday' => $today->isoFormat('dddd'),
        ];

        if (AppModules::isVisible($user, 'schedule')) {
            ScheduleEvent::materializeRange($user, $today, $today->copy());

            $overview['schedule'] = ScheduleEvent::forUser($user)->visible()->forDay($today)->ordered()->with('category')->get()
                ->map(fn (ScheduleEvent $e) => [
                    'id' => $e->id,
                    'title' => $e->displayTitle(),
                    'kind' => $e->isCategory() ? 'category' : 'appointment',
                    'start_time' => $e->start_time,
                    'end_time' => $e->end_time,
                    'buffer_before' => (int) $e->buffer_before,
                    'buffer_after' => (int) $e->buffer_after,
                    'pomodoro_enabled' => (bool) $e->category?->pomodoro_enabled,
                ])->values()->all();
        }

        $overview['today_tasks'] = TaskResource::collection(
            Task::query()->forUser($user)->onBoard()->active()->where('is_today', true)->boardOrdered()->get()
        )->resolve();

        $dueTasks = Task::query()->forUser($user)->active()
            ->where(fn ($q) => $q->whereNotNull('deadline')->orWhereNotNull('due_date'))
            ->get()
            ->filter(fn (Task $t) => $t->effectiveDate() !== null && ! $t->effectiveDate()->greaterThan($soonCutoff))
            ->sortBy(fn (Task $t) => $t->effectiveDate()->timestamp)
            ->values();

        $bucket = fn ($date, bool $overdue) => $overdue ? 'overdue' : ($date->isSameDay($today) ? 'today' : 'soon');

        $overview['due'] = [
            'tasks' => $dueTasks->map(fn (Task $t) => [
                'id' => $t->id,
                'title' => $t->title,
                'date' => $t->effectiveDate()->toDateString(),
                'label' => $t->effectiveDateLabel(),
                'bucket' => $bucket($t->effectiveDate(), $t->isOverdue()),
                'is_hard_deadline' => $t->deadline !== null,
            ])->all(),
        ];

        if (AppModules::isVisible($user, 'agenda')) {
            $overview['due']['agenda'] = AgendaEntry::query()->visibleTo($user)->openFor($user)->ordered()->get()
                ->filter(fn (AgendaEntry $e) => ! $e->date->greaterThan($soonCutoff))
                ->map(fn (AgendaEntry $e) => [
                    'id' => $e->id,
                    'type' => $e->type,
                    'subject' => $e->subject,
                    'title' => $e->title,
                    'date' => $e->date->toDateString(),
                    'label' => $e->dateLabel(),
                    'bucket' => $bucket($e->date, $e->isOverdue()),
                ])->values()->all();

            $overview['upcoming_homework'] = AgendaEntry::homeworkPreviewFor($user)->map(fn (AgendaEntry $e) => [
                'id' => $e->id,
                'subject' => $e->subject,
                'title' => $e->title,
                'date' => $e->date->toDateString(),
                'label' => $e->dateLabel(),
                'duration_minutes' => $e->duration_minutes,
            ])->values()->all();
        }

        if ($user->planner_enabled) {
            $day = DayPlanner::board($user)->get($today->toDateString());

            $overview['planner_today'] = $day === null ? null : [
                'capacity_minutes' => $day['capacityTotal'],
                'planned_minutes' => $day['capacityUsed'],
                'tasks' => $day['tasks']->map(fn (array $i) => ['type' => $i['type'], 'id' => $i['id'], 'title' => $i['title'], 'duration_minutes' => $i['duration']])->values()->all(),
            ];
        }

        $emergency = DayPreviewData::emergency($user);
        if ($emergency !== null) {
            $overview['emergency'] = $emergency;
        }

        if (AppModules::isVisible($user, 'progress')) {
            $overview['goal'] = DayPreviewData::goal($user);
        }

        return $overview;
    }
}
