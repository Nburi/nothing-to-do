<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\McpAbility;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class CreateHelpArticleTool extends AdminTool
{
    public function name(): string
    {
        return 'create_help_article';
    }

    public function description(): string
    {
        return 'ADMIN: create a Hilfe-Center article as a DRAFT. It is never published through MCP — the admin '
            .'reviews and publishes it in the admin area. Body is GitHub-flavoured Markdown (German, like the rest of the app).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'content' => ['type' => 'string', 'description' => 'Markdown body.'],
                'category_id' => ['type' => 'integer', 'description' => 'From list_help_categories. Omit for "Ohne Kategorie".'],
            ],
            'required' => ['title'],
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
            'title' => ['required', 'string', 'max:255'],
            'content' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:'.(new HelpCategory)->getTable().',id'],
        ])->validate();

        $title = trim($data['title']);
        $content = isset($data['content']) ? trim($data['content']) : '';
        $categoryId = $data['category_id'] ?? null;

        $article = $user->createdHelpArticles()->create([
            'title' => $title !== '' ? $title : 'Neuer Artikel',
            'slug' => HelpArticle::generateSlug($title !== '' ? $title : 'Neuer Artikel'),
            'content' => $content !== '' ? $content : null,
            'help_category_id' => $categoryId,
            'is_published' => false,
            'sort_order' => HelpArticle::where('help_category_id', $categoryId)->count(),
        ]);

        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'category_id' => $article->help_category_id,
            'is_published' => false,
        ];
    }
}
