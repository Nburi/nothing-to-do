<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\McpAbility;
use App\Models\User;

class DeleteHelpArticleTool extends AdminTool
{
    public function name(): string
    {
        return 'delete_help_article';
    }

    public function description(): string
    {
        return 'ADMIN: PERMANENTLY delete a DRAFT Hilfe-Center article. Irreversible. Pass confirm_title matching '
            .'its exact current title; always confirm with the user first. Refused for published articles.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'confirm_title' => ['type' => 'string', 'description' => 'Must exactly match the article\'s current title.'],
            ],
            'required' => ['id', 'confirm_title'],
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
        $article = $this->helpArticle($arguments['id'] ?? null);
        $this->publishedGuard($article->is_published, 'article', 'deleted');
        $this->confirmTitle($article->title, $arguments['confirm_title'] ?? null);

        $id = $article->id;
        $title = $article->title;
        $article->delete();

        return ['deleted' => true, 'id' => $id, 'title' => $title];
    }
}
