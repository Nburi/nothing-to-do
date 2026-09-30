<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Models\HelpCategory;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class CreateHelpCategoryTool extends AdminTool
{
    public function name(): string
    {
        return 'create_help_category';
    }

    public function description(): string
    {
        return 'ADMIN: create a Hilfe-Center category (a top-level folder, or a subfolder when parent_id is given). '
            .'Only two levels exist, so the parent must be a top-level category.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'parent_id' => ['type' => 'integer', 'description' => 'A top-level category id from list_help_categories. Omit for a top-level folder.'],
            ],
            'required' => ['name'],
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
        $data = Validator::make($arguments, [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', 'integer'],
        ])->validate();

        $name = trim($data['name']);
        if ($name === '') {
            throw new McpToolExecutionException('name must not be empty.');
        }

        $parent = $this->parentCategory($data['parent_id'] ?? null);

        $category = HelpCategory::create([
            'name' => $name,
            'parent_id' => $parent?->id,
            'sort_order' => $parent !== null
                ? $parent->children()->count()
                : HelpCategory::roots()->count(),
        ]);

        return ['id' => $category->id, 'name' => $category->name, 'parent_id' => $category->parent_id];
    }
}
