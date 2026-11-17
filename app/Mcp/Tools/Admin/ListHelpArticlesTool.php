<?php

namespace App\Mcp\Tools\Admin;

use App\Models\HelpArticle;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class ListHelpArticlesTool extends AdminTool
{
    public function name(): string
    {
        return 'list_help_articles';
    }

    public function description(): string
    {
        return 'ADMIN: list Hilfe-Center articles, drafts included, with publish state and a short preview. '
            .'Optionally search title/body or filter by category. Use get_help_article for the full Markdown.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'search' => ['type' => 'string'],
                'category_id' => ['type' => 'integer'],
                'only_drafts' => ['type' => 'boolean'],
                'limit' => ['type' => 'integer', 'description' => 'Max results, default 50, max 200.'],
            ],
        ];
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $data = Validator::make($arguments, [
            'search' => ['sometimes', 'string', 'max:255'],
            'category_id' => ['sometimes', 'integer'],
            'only_drafts' => ['sometimes', 'boolean'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ])->validate();

        $articles = HelpArticle::query()
            ->search($data['search'] ?? null)
            ->when(isset($data['category_id']), fn ($q) => $q->where('help_category_id', $data['category_id']))
            ->when($data['only_drafts'] ?? false, fn ($q) => $q->where('is_published', false))
            ->orderBy('help_category_id')->orderBy('sort_order')->orderBy('id')
            ->limit($data['limit'] ?? 50)
            ->get();

        return [
            'articles' => $articles->map(fn (HelpArticle $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'slug' => $a->slug,
                'category_id' => $a->help_category_id,
                'is_published' => $a->is_published,
                'preview' => $a->preview(),
            ])->values()->all(),
        ];
    }
}
