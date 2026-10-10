<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\CategoryAttributeFields;
use App\Models\User;

class DeleteCategoryAttributeTool extends McpTool
{
    use CategoryAttributeFields;

    public function name(): string
    {
        return 'delete_category_attribute';
    }

    public function description(): string
    {
        return 'PERMANENTLY delete a category\'s custom field together with ALL values ever entered for it on '
            .'Zeitplan entries. Pass confirm_name matching the attribute\'s exact name (list_categories). '
            .'Always confirm with the user first.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'category_id' => ['type' => 'integer'],
                'attribute_id' => ['type' => 'integer'],
                'confirm_name' => ['type' => 'string', 'description' => 'Must exactly match the attribute\'s current name.'],
            ],
            'required' => ['category_id', 'attribute_id', 'confirm_name'],
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
        $category = $this->ownedCategory($user, $arguments['category_id'] ?? null);
        $attribute = $category->customAttributes()->find((int) ($arguments['attribute_id'] ?? 0));

        if ($attribute === null) {
            throw new McpToolExecutionException('No attribute with id '.($arguments['attribute_id'] ?? '?')." on category {$category->id}.");
        }

        if (($arguments['confirm_name'] ?? null) !== $attribute->name) {
            throw new McpToolExecutionException(
                "confirm_name did not match. This attribute's current name is: \"{$attribute->name}\". ".
                'Pass that exact string as confirm_name to proceed, after checking with the user.'
            );
        }

        $id = $attribute->id;
        $name = $attribute->name;
        $attribute->delete();

        return ['deleted' => true, 'id' => $id, 'name' => $name];
    }
}
