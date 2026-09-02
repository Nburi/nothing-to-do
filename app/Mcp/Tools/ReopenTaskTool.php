<?php

namespace App\Mcp\Tools;

use App\Http\Resources\TaskResource;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;
use App\Support\TaskMutator;

class ReopenTaskTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'reopen_task';
    }

    public function description(): string
    {
        return 'Mark a completed task as not done again.';
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
        $task = TaskMutator::applyUpdate($task, $user, ['is_completed' => false]);

        return (new TaskResource($task))->resolve();
    }
}
