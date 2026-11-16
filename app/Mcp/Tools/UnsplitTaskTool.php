<?php

namespace App\Mcp\Tools;

use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\PlannerToolDefaults;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;
use App\Services\DayPlanner;

class UnsplitTaskTool extends McpTool
{
    use PlannerToolDefaults;
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'unsplit_task';
    }

    public function description(): string
    {
        return 'End a task\'s split: every part (finished ones included) is removed and the task returns to the '
            .'Planer\'s backlog, unplanned. The task itself and its notes are untouched. To drop just one open part use unplan_task with session_id.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['task_id' => ['type' => 'integer']],
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

        DayPlanner::unsplit($user, $task);

        return ['unsplit' => true, 'task_id' => $task->id];
    }
}
