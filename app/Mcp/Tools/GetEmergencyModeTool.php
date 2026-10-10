<?php

namespace App\Mcp\Tools;

use App\Mcp\McpTool;
use App\Models\User;

class GetEmergencyModeTool extends McpTool
{
    public function name(): string
    {
        return 'get_emergency_mode';
    }

    public function description(): string
    {
        return 'Notfallmodus ("this project is due very soon"): whether it is active and for which project, the '
            .'project\'s open tasks in the order they will be done, and for each the board column (inbox/todos/tasks) '
            .'it surfaces under while the mode is on. Also lists all projects so one can be picked '
            .'(start_emergency_mode). While active the board narrows to this project plus anything important.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) []];
    }

    public function requiredModule(): ?string
    {
        return 'emergency';
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $active = $user->isInEmergencyMode() ? $user->projects()->find($user->emergency_project_id) : null;

        $tasks = $active === null ? [] : $active->tasks()->active()->orderBy('sort_order')->orderBy('created_at')->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'emergency_list' => $t->emergency_list,
                'deadline' => $t->deadline?->toDateString(),
            ])->values()->all();

        return [
            'active' => $active !== null,
            'project' => $active === null ? null : ['id' => $active->id, 'name' => $active->name],
            'tasks' => $tasks,
            'projects' => $user->projects()->ordered()
                ->withCount(['tasks as active_task_count' => fn ($q) => $q->where('is_completed', false)])->get()
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'deadline' => $p->deadline?->toDateString(), 'active_task_count' => $p->active_task_count])
                ->values()->all(),
        ];
    }
}
