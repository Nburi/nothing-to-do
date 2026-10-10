<?php

namespace App\Mcp\Tools;

use App\Http\Resources\TaskGroupResource;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;

class GetTaskGroupTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'get_task_group';
    }

    public function description(): string
    {
        return 'Get one task group with all its tasks and its note cards (Markdown). '
            .'list_task_groups only returns a summary.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'integer']],
            'required' => ['id'],
        ];
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $group = $this->ownedTaskGroup($user, $arguments['id'] ?? null);
        $group->loadCount([
            'tasks as active_count' => fn ($q) => $q->where('is_completed', false),
            'tasks as done_count' => fn ($q) => $q->where('is_completed', true),
        ])->load('tasks');

        return (new TaskGroupResource($group))->resolve() + [
            'notes' => $group->notes->map(fn ($note) => [
                'id' => $note->id,
                'content' => $note->content,
            ])->values()->all(),
        ];
    }
}
