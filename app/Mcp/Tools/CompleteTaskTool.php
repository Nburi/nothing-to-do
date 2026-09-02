<?php

namespace App\Mcp\Tools;

use App\Http\Resources\TaskResource;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;
use App\Support\TaskMutator;

/**
 * A focused completion toggle — same effect as update_task with
 * is_completed=true, but a clearer signal of intent for a model deciding
 * which tool to call, and it also syncs a linked Agenda homework entry the
 * exact same way the app's own checkbox does (Task::syncLinkedAgendaEntry).
 */
class CompleteTaskTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'complete_task';
    }

    public function description(): string
    {
        return 'Mark a task as done.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'integer']],
            'required' => ['id'],
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
        $task = $this->ownedTask($user, $arguments['id'] ?? null);
        $task = TaskMutator::applyUpdate($task, $user, ['is_completed' => true]);

        return (new TaskResource($task))->resolve();
    }
}
