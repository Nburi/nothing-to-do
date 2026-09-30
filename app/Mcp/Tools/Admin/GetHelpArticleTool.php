<?php

namespace App\Mcp\Tools\Admin;

use App\Models\User;

class GetHelpArticleTool extends AdminTool
{
    public function name(): string
    {
        return 'get_help_article';
    }

    public function description(): string
    {
        return 'ADMIN: read one Hilfe-Center article in full (Markdown body), drafts included.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'integer']],
            'required' => ['id'],
        ];
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $a = $this->helpArticle($arguments['id'] ?? null);

        return [
            'id' => $a->id,
            'title' => $a->title,
            'slug' => $a->slug,
            'category_id' => $a->help_category_id,
            'is_published' => $a->is_published,
            'published_at' => $a->published_at?->toIso8601String(),
            'content' => $a->content,
        ];
    }
}
