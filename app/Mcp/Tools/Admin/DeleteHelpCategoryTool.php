<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Models\User;

class DeleteHelpCategoryTool extends AdminTool
{
    public function name(): string
    {
        return 'delete_help_category';
    }

    public function description(): string
    {
        return 'ADMIN: delete a Hilfe-Center category. Its draft articles fall back to "Ohne Kategorie" and its '
            .'subcategories become top-level — nothing else is lost. Pass confirm_name matching the exact name; '
            .'always confirm with the user first. Refused while the category or its subcategories hold published articles.';
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
        $category = $this->helpCategory($arguments['id'] ?? null);

        if ($this->categoryIsLive($category)) {
            throw new McpToolExecutionException(
                'This category contains published articles, so it cannot be deleted through MCP — that would pull '
                .'live articles out of their folder. Ask the admin to do it in the admin area.'
            );
        }

        $given = $arguments['confirm_name'] ?? null;
        if (! is_string($given) || $given !== $category->name) {
            throw new McpToolExecutionException(
                "confirm_name did not match. The current name is: \"{$category->name}\". "
                .'Pass that exact string as confirm_name to proceed, after checking with the user.'
            );
        }

        $id = $category->id;
        $name = $category->name;
        $category->delete();

        return ['deleted' => true, 'id' => $id, 'name' => $name];
    }
}
