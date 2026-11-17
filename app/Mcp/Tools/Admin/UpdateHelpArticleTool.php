<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\McpAbility;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class UpdateHelpArticleTool extends AdminTool
{
    public function name(): string
    {
        return 'update_help_article';
    }

    public function description(): string
    {
        return 'ADMIN: edit a DRAFT Hilfe-Center article (title, Markdown body, category). Only the fields you '
            .'pass change. Refused for already-published articles — editing those would change live content; '
            .'the admin must unpublish first. Cannot publish.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'content' => ['type' => 'string', 'description' => 'Full replacement Markdown body.'],
                'category_id' => ['type' => ['integer', 'null'], 'description' => 'null = "Ohne Kategorie".'],
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
        $article = $this->helpArticle($arguments['id'] ?? null);
        $this->publishedGuard($article->is_published, 'article', 'edited');

        $data = Validator::make($arguments, [
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:'.(new HelpCategory)->getTable().',id'],
        ])->validate();

        $attributes = [];

        if (array_key_exists('title', $data)) {
            $title = trim($data['title']);
            if ($title !== '') {
                $attributes['title'] = $title;
                // A draft's slug follows its title, exactly like the admin editor.
                $attributes['slug'] = HelpArticle::generateSlug($title, $article->id);
            }
        }
        if (array_key_exists('content', $data)) {
            $content = trim((string) $data['content']);
            $attributes['content'] = $content !== '' ? $content : null;
        }
        if (array_key_exists('category_id', $data)) {
            $attributes['help_category_id'] = $data['category_id'];
        }

        $article->update($attributes);

        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'category_id' => $article->help_category_id,
            'is_published' => $article->is_published,
        ];
    }
}
