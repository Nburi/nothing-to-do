<?php

namespace App\Mcp\Tools;

use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\PlannerToolDefaults;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;
use App\Services\DayPlanner;

class UnplanTaskTool extends McpTool
{
    use PlannerToolDefaults, ResolvesOwnedModel;

    public function name(): string
    {
        return 'unplan_task';
    }

    public function description(): string
    {
        return 'Release a task from its planned day, back to the Planer backlog. The task itself is untouched '
            .'(including a "Heute" flag it may already have).';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['task_id' => ['type' => 'integer']], 'required' => ['task_id']];
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

        DayPlanner::unassignTask($task);

        return ['unplanned' => true, 'task_id' => $task->id];
    }
}
