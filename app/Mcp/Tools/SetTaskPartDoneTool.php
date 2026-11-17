<?php

namespace App\Mcp\Tools;

use App\Http\Resources\TaskResource;
use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\PlannerToolDefaults;
use App\Models\TaskSession;
use App\Models\User;
use App\Services\DayPlanner;

class SetTaskPartDoneTool extends McpTool
{
    use PlannerToolDefaults;

    public function name(): string
    {
        return 'set_task_part_done';
    }

    public function description(): string
    {
        return 'Tick one part of a split task off (done: true, the default) or back open (done: false). Finishing the part '
            .'that put the task on "Heute" takes it off Heute again. Never completes the task itself — when every part is done, '
            .'tell the user and use complete_task only if they agree. Part ids come from get_task (sessions) or get_planner.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'session_id' => ['type' => 'integer', 'description' => 'The part\'s id.'],
                'done' => ['type' => 'boolean', 'default' => true],
            ],
            'required' => ['session_id'],
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
        $part = TaskSession::query()->whereHas('task', fn ($q) => $q->forUser($user)->active())->find((int) ($arguments['session_id'] ?? 0));

        if ($part === null) {
            throw new McpToolExecutionException('No part with id '.($arguments['session_id'] ?? '?').' found on an open task of this account.');
        }

        DayPlanner::completeSession($user, $part->id, (bool) ($arguments['done'] ?? true));

        return (new TaskResource($part->task->fresh()->load('sessions')))->resolve();
    }
}
