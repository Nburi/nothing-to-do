<?php

namespace App\Mcp\Tools;

use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\CategoryAttributeFields;
use App\Models\User;

class CreateCategoryAttributeTool extends McpTool
{
    use CategoryAttributeFields;

    public function name(): string
    {
        return 'create_category_attribute';
    }

    public function description(): string
    {
        return 'Define a custom field on a category (e.g. "Trainingstyp" as a select with Lauf/Kraft, or "Dauer" as a '
            .'number with unit "Min"). Values are then entered per Zeitplan entry (attribute_values on '
            .'create_/update_schedule_event) and shown on the block. Never required.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['category_id' => ['type' => 'integer']] + self::attributeProperties(),
            'required' => ['category_id', 'name', 'type'],
        ];
    }

    public function requiredAbility(): ?string
    {
        return McpAbility::WRITE;
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false];
    }

    public function handle(User $user, array $arguments): array
    {
        $category = $this->ownedCategory($user, $arguments['category_id'] ?? null);
        $payload = self::attributePayload($arguments, false);

        $attribute = $category->customAttributes()->create($payload + [
            'options' => null,
            'unit' => null,
            'sort_order' => $category->customAttributes()->count(),
        ]);

        return self::presentAttribute($attribute->fresh());
    }
}
