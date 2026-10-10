<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\CategoryAttributeFields;
use App\Models\User;

class UpdateCategoryAttributeTool extends McpTool
{
    use CategoryAttributeFields;

    public function name(): string
    {
        return 'update_category_attribute';
    }

    public function description(): string
    {
        return 'Change a category\'s custom field: rename it, change its unit, or replace its select options. '
            .'Values already entered are kept as they are (an option removed here simply stops matching its colour). '
            .'Changing the type keeps stored values but they are only shown when they fit the new type.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['category_id' => ['type' => 'integer'], 'attribute_id' => ['type' => 'integer']] + self::attributeProperties(),
            'required' => ['category_id', 'attribute_id'],
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
        $category = $this->ownedCategory($user, $arguments['category_id'] ?? null);
        $attribute = $category->customAttributes()->find((int) ($arguments['attribute_id'] ?? 0));

        if ($attribute === null) {
            throw new McpToolExecutionException('No attribute with id '.($arguments['attribute_id'] ?? '?')." on category {$category->id}.");
        }

        $payload = self::attributePayload($arguments, true, $attribute->type);
        $attribute->update($payload);

        return self::presentAttribute($attribute->fresh());
    }
}
