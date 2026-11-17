<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpTool;
use App\Models\HelpArticle;
use App\Models\User;

class ReadHelpArticleTool extends McpTool
{
    public function name(): string
    {
        return 'read_help_article';
    }

    public function description(): string
    {
        return 'Read one published Hilfe-Center article in full (Markdown). Find ids with search_help.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']];
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $article = HelpArticle::query()->published()->with('category')->find((int) ($arguments['id'] ?? 0));

        if ($article === null) {
            throw new McpToolExecutionException('No published help article with id '.($arguments['id'] ?? '?').' found.');
        }

        return [
            'id' => $article->id,
            'title' => $article->title,
            'category' => $article->category?->name,
            'content' => $article->content,
            'published_at' => $article->published_at?->toIso8601String(),
        ];
    }
}
