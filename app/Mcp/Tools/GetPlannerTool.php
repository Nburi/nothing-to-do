<?php

namespace App\Mcp\Tools;

use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\PlannerToolDefaults;
use App\Models\User;
use App\Services\DayPlanner;

class GetPlannerTool extends McpTool
{
    use PlannerToolDefaults;

    public function name(): string
    {
        return 'get_planner';
    }

    public function description(): string
    {
        return 'The Planer: which task is planned for which of the next '.DayPlanner::HORIZON_DAYS.' days (in order), '
            .'each day\'s capacity (minutes of Pomodoro blocks) versus planned minutes, the backlog of tasks and '
            .'open homework with no day yet, conflicts (backlog items whose deadline — minus a 2-day buffer — has '
            .'already passed) and "rollover" (planned days that passed without the task reaching today\'s list). '
            .'A missing duration estimate is counted with a default (To-Do 10, Task/Projekt/homework 25 min) — '
            .'see has_estimate and set real ones with update_task(duration_minutes).';
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
        $days = DayPlanner::board($user)->map(fn (array $day) => [
            'date' => $day['date']->toDateString(),
            'weekday' => $day['date']->isoFormat('dd'),
            'capacity_minutes' => $day['capacityTotal'],
            'planned_minutes' => $day['capacityUsed'],
            'blocks' => $day['blockLabels'],
            'tasks' => $day['tasks']->map(fn (array $i) => self::presentItem($i))->values()->all(),
        ])->values()->all();

        $present = fn ($items) => $items->map(fn (array $i) => self::presentItem($i))->values()->all();

        return [
            'horizon_days' => DayPlanner::HORIZON_DAYS,
            'days' => $days,
            'backlog' => $present(DayPlanner::backlog($user)),
            'conflicts' => $present(DayPlanner::conflicts($user)),
            'rollover' => $present(DayPlanner::rollover($user)),
        ];
    }
}
