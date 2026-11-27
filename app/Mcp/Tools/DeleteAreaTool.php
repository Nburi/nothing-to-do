<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\User;

/** Areas (api parity). Deleting an area only removes the label: no task is deleted. */
class DeleteAreaTool extends McpTool
{
    public function name(): string
    {
        return 'delete_area';
    }

    public function description(): string
    {
        return 'Delete an area. No task is deleted: tasks that were in it simply have no area afterwards, and categories lose the suggestion. Pass confirm_name matching the area\'s exact name, and check with the user first.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'confirm_name' => ['type' => 'string', 'description' => 'Must exactly match the area\'s current name.'],
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
        $area = $user->areas()->find((int) ($arguments['id'] ?? 0));
        if ($area === null) {
            throw new McpToolExecutionException('No area with id '.($arguments['id'] ?? '?').' found for this account.');
        }
        if (($arguments['confirm_name'] ?? null) !== $area->name) {
            throw new McpToolExecutionException(
                "confirm_name did not match. This area's current name is: \"{$area->name}\". ".
                'Pass that exact string as confirm_name to proceed, after checking with the user.'
            );
        }

        $area->delete();

        return ['deleted' => true, 'id' => $area->id, 'name' => $area->name];
    }
}
