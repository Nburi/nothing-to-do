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
            .'(including a "Heute" flag it may already have). For a split task, task_id drops every part (like unsplit_task); '
            .'session_id drops just that one open part and leaves the others.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => [
            'task_id' => ['type' => 'integer'],
            'session_id' => ['type' => 'integer', 'description' => 'One open part of a split task instead of a task.'],
        ]];
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
        $taskId = $arguments['task_id'] ?? null;
        $sessionId = $arguments['session_id'] ?? null;

        if (($taskId === null) === ($sessionId === null)) {
            throw new \App\Mcp\Exceptions\McpToolExecutionException('Give exactly one of task_id or session_id.');
        }

        if ($sessionId !== null) {
            $token = self::plannerToken($user, ['session_id' => $sessionId]);
            DayPlanner::removeSession($user, (int) substr($token, 8));

            return ['unplanned' => true, 'session_id' => (int) $sessionId];
        }

        $task = $this->ownedTask($user, $taskId);

        DayPlanner::unassignTask($task);

        return ['unplanned' => true, 'task_id' => $task->id];
    }
}
