<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ScheduleToolDefaults;
use App\Models\User;

class DeleteCategoryTool extends McpTool
{
    use ScheduleToolDefaults;

    public function name(): string
    {
        return 'delete_category';
    }

    public function description(): string
    {
        return 'PERMANENTLY delete a category together with its custom attribute definitions and ALL values entered '
            .'for them. Existing Zeitplan blocks of the category are kept (they fall back to their stored title and '
            .'colour). Pass confirm_name matching the category\'s exact name. Always confirm with the user first.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'confirm_name' => ['type' => 'string', 'description' => 'Must exactly match the category\'s current name.'],
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
        $category = $user->eventCategories()->find((int) ($arguments['id'] ?? 0));

        if ($category === null) {
            throw new McpToolExecutionException('No category with id '.($arguments['id'] ?? '?').' found for this account.');
        }

        if (($arguments['confirm_name'] ?? null) !== $category->name) {
            throw new McpToolExecutionException(
                "confirm_name did not match. This category's current name is: \"{$category->name}\". ".
                'Pass that exact string as confirm_name to proceed, after checking with the user.'
            );
        }

        $id = $category->id;
        $name = $category->name;
        $category->delete();

        return ['deleted' => true, 'id' => $id, 'name' => $name];
    }
}
