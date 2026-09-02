<?php

namespace App\Mcp\Tools;

use App\Mcp\McpTool;
use App\Models\User;

class ListTaskGroupsTool extends McpTool
{
    public function name(): string
    {
        return 'list_task_groups';
    }

    public function description(): string
    {
        return 'List this user\'s task groups (bundles of a few related tasks), each with its progress and next task.';
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
        $groups = $user->taskGroups()->ordered()->withCount([
            'tasks as active_task_count' => fn ($q) => $q->where('is_completed', false),
            'tasks as total_task_count',
        ])->get();

        return [
            'task_groups' => $groups->map(fn ($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'active_task_count' => $group->active_task_count,
                'total_task_count' => $group->total_task_count,
                'next_task' => optional($group->activeTasks()->first())->title,
            ])->values()->all(),
        ];
    }
}
