<?php

namespace App\Mcp\Tools;

use App\Http\Resources\TaskResource;
use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\PlannerToolDefaults;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;
use App\Services\DayPlanner;

class SplitTaskTool extends McpTool
{
    use PlannerToolDefaults;
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'split_task';
    }

    public function description(): string
    {
        return 'Spread one long task over several days as separate work parts (the Planer\'s "Aufteilen"). '
            .'Give the days yourself (dates) or let the app propose them (parts) — it prefers days whose work blocks '
            .'still have room, stays before the deadline and spreads dated tasks evenly. total_minutes is the work '
            .'still to be planned, divided evenly over the open parts (default: the task\'s estimate). At most one part '
            .'per day, at least two parts in total; finished parts are never touched, so this also re-plans an existing '
            .'split. A part planned for today puts the task on "Heute". Ticking parts off is set_task_part_done; '
            .'plan_task with session_id moves a single part.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'task_id' => ['type' => 'integer'],
                'dates' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'format' => 'date'],
                    'description' => 'The days (YYYY-MM-DD, today … today+'.(DayPlanner::HORIZON_DAYS - 1).') to put one part on each.',
                ],
                'parts' => ['type' => 'integer', 'minimum' => 1, 'maximum' => DayPlanner::HORIZON_DAYS, 'description' => 'Instead of dates: how many parts — the app picks the days.'],
                'total_minutes' => ['type' => 'integer', 'minimum' => DayPlanner::MIN_PART_MINUTES, 'description' => 'Work still to be planned, in minutes.'],
            ],
            'required' => ['task_id'],
        ];
    }

    public function requiredAbility(): ?string
    {
        return McpAbility::WRITE;
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $task = $this->ownedTask($user, $arguments['task_id'] ?? null);
        $context = DayPlanner::splitContext($user, $task);

        if ($context === null) {
            throw new McpToolExecutionException('This task cannot be split: it must be an open To-Do, Task or project task.');
        }

        $dates = $arguments['dates'] ?? null;
        $parts = $arguments['parts'] ?? null;

        if (($dates === null) === ($parts === null)) {
            throw new McpToolExecutionException('Give exactly one of dates or parts.');
        }

        $total = (int) ($arguments['total_minutes'] ?? $context['defaultTotal']);
        $dates ??= DayPlanner::suggestSplitDates($user, $task, (int) $parts, $total);

        if (! is_array($dates) || ! DayPlanner::saveSplit($user, $task, array_values($dates), $total)) {
            throw new McpToolExecutionException(
                'Could not split: pick at least two different days between today and today+'.(DayPlanner::HORIZON_DAYS - 1)
                .' (finished parts count towards the two), none of them on a day that already has a finished part.'
            );
        }

        return (new TaskResource($task->fresh()->load('sessions')))->resolve();
    }
}
