<?php

namespace App\Mcp\Tools\Admin;

use App\Models\HelpCategory;
use App\Models\User;

class ListHelpCategoriesTool extends AdminTool
{
    public function name(): string
    {
        return 'list_help_categories';
    }

    public function description(): string
    {
        return 'ADMIN: list the Hilfe-Center categories (id, name, parent_id) — needed to file an article into one. '
            .'Categories themselves can only be managed in the admin area.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) []];
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        return [
            'categories' => HelpCategory::orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (HelpCategory $c) => ['id' => $c->id, 'name' => $c->name, 'parent_id' => $c->parent_id])
                ->values()->all(),
        ];
    }
}
