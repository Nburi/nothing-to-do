<?php

namespace App\Mcp\Tools;

use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class UpdateTaskGroupTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'update_task_group';
    }

    public function description(): string
    {
        return 'Rename a task group. To move tasks in or out use update_task(group_id) — null releases a task.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
            ],
            'required' => ['id', 'name'],
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
        $group = $this->ownedTaskGroup($user, $arguments['id'] ?? null);

        $data = Validator::make($arguments, ['name' => ['required', 'string', 'max:255']])->validate();

        $group->update(['name' => trim($data['name'])]);

        return ['id' => $group->id, 'name' => $group->name];
    }
}
