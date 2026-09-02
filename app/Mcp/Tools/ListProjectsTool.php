<?php

namespace App\Mcp\Tools;

use App\Mcp\McpTool;
use App\Models\User;

class ListProjectsTool extends McpTool
{
    public function name(): string
    {
        return 'list_projects';
    }

    public function description(): string
    {
        return 'List this user\'s projects, each with its progress (done/total active tasks) and next task.';
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
        $projects = $user->projects()->ordered()->withCount([
            'tasks as active_task_count' => fn ($q) => $q->where('is_completed', false),
            'tasks as total_task_count',
        ])->get();

        return [
            'projects' => $projects->map(fn ($project) => [
                'id' => $project->id,
                'name' => $project->name,
                'deadline' => $project->deadline?->toDateString(),
                'deadline_label' => $project->deadline ? $project->deadlineLabel() : null,
                'is_overdue' => $project->isOverdue(),
                'external_url' => $project->external_url,
                'active_task_count' => $project->active_task_count,
                'total_task_count' => $project->total_task_count,
                'next_task' => optional($project->activeTasks()->first())->title,
            ])->values()->all(),
        ];
    }
}
