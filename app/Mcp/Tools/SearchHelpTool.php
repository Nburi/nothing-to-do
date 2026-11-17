<?php

namespace App\Mcp\Tools;

use App\Mcp\McpTool;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class SearchHelpTool extends McpTool
{
    public function name(): string
    {
        return 'search_help';
    }

    public function description(): string
    {
        return 'Search the app\'s Hilfe-Center (published articles only) — use it to answer "how do I … in nothing-to-do" '
            .'questions from the real docs instead of guessing. Without a query it returns the full category tree '
            .'with article titles. Read an article in full with read_help_article.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Words from the title or the body.'],
                'limit' => ['type' => 'integer', 'description' => 'Max results, default 10, max 30.'],
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
            'query' => ['sometimes', 'nullable', 'string', 'max:255'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:30'],
        ])->validate();

        $term = trim((string) ($data['query'] ?? ''));

        if ($term === '') {
            return [
                'categories' => HelpCategory::tree(true)->map(fn (HelpCategory $c) => [
                    'name' => $c->name,
                    'articles' => $c->articles->map(fn (HelpArticle $a) => ['id' => $a->id, 'title' => $a->title])->values()->all(),
                    'subcategories' => $c->children->map(fn (HelpCategory $sub) => [
                        'name' => $sub->name,
                        'articles' => $sub->articles->map(fn (HelpArticle $a) => ['id' => $a->id, 'title' => $a->title])->values()->all(),
                    ])->values()->all(),
                ])->values()->all(),
            ];
        }

        $articles = HelpArticle::query()->published()->search($term)
            ->with('category')
            ->orderBy('title')
            ->limit($data['limit'] ?? 10)
            ->get();

        return [
            'count' => $articles->count(),
            'articles' => $articles->map(fn (HelpArticle $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'category' => $a->category?->name,
                'excerpt' => mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) $a->content))), 0, 200),
            ])->values()->all(),
        ];
    }
}
