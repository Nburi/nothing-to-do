<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Models\HelpCategory;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class UpdateHelpCategoryTool extends AdminTool
{
    public function name(): string
    {
        return 'update_help_category';
    }

    public function description(): string
    {
        return 'ADMIN: rename a Hilfe-Center category and/or move it under another top-level category (parent_id null '
            .'= make it top-level). Refused while the category or its subcategories hold published articles — that '
            .'would change the live sidebar; the admin must do it in the admin area.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'parent_id' => ['type' => ['integer', 'null']],
            ],
            'required' => ['id'],
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
        $category = $this->helpCategory($arguments['id'] ?? null);

        if ($this->categoryIsLive($category)) {
            throw new McpToolExecutionException(
                'This category contains published articles, so it cannot be changed through MCP — that would change '
                .'the live Hilfe-Center. Ask the admin to do it in the admin area.'
            );
        }

        $data = Validator::make($arguments, [
            'name' => ['sometimes', 'string', 'max:255'],
            'parent_id' => ['sometimes', 'nullable', 'integer'],
        ])->validate();

        $attributes = [];

        if (array_key_exists('name', $data)) {
            $name = trim($data['name']);
            if ($name === '') {
                throw new McpToolExecutionException('name must not be empty.');
            }
            $attributes['name'] = $name;
        }

        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== $category->parent_id) {
            $parent = $this->parentCategory($data['parent_id']);

            if ($parent !== null && ($parent->id === $category->id || $category->children()->exists())) {
                throw new McpToolExecutionException('A category with subcategories (or itself) cannot become a subcategory — only two levels exist.');
            }

            $attributes['parent_id'] = $parent?->id;
            $attributes['sort_order'] = $parent !== null ? $parent->children()->count() : HelpCategory::roots()->count();
        }

        $category->update($attributes);

        return ['id' => $category->id, 'name' => $category->name, 'parent_id' => $category->parent_id];
    }
}
