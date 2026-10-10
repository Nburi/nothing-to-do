<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;

/**
 * Tasks are never lost (group_id is nulled by the FK), but the group's note
 * cards cascade-delete with it — which is why this sits behind mcp:delete.
 */
class DissolveTaskGroupTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'dissolve_task_group';
    }

    public function description(): string
    {
        return 'Dissolve a task group. Its tasks stay exactly where they are and simply become loose on the '
            .'board again, but the group\'s NOTE CARDS are permanently deleted. You must pass confirm_name '
            .'matching the group\'s exact current name. Always confirm with the user first.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'confirm_name' => ['type' => 'string', 'description' => 'Must exactly match the group\'s current name.'],
            ],
            'required' => ['id', 'confirm_name'],
        ];
    }

    public function requiredAbility(): ?string
    {
        return McpAbility::DELETE;
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false];
    }

    public function handle(User $user, array $arguments): array
    {
        $group = $this->ownedTaskGroup($user, $arguments['id'] ?? null);
        $confirm = $arguments['confirm_name'] ?? null;

        if (! is_string($confirm) || $confirm !== $group->name) {
            throw new McpToolExecutionException(
                "confirm_name did not match. This group's current name is: \"{$group->name}\". ".
                'Pass that exact string as confirm_name to proceed, after checking with the user.'
            );
        }

        $id = $group->id;
        $name = $group->name;
        $group->delete();

        return ['dissolved' => true, 'id' => $id, 'name' => $name];
    }
}
