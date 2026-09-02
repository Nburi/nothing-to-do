<?php

namespace App\Mcp\Tools;

use App\Http\Resources\TaskResource;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;

class GetTaskTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'get_task';
    }

    public function description(): string
    {
        return 'Get one task by id, including its full notes.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
            ],
            'required' => ['id'],
        ];
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $task = $this->ownedTask($user, $arguments['id'] ?? null);

        return (new TaskResource($task))->resolve();
    }
}
