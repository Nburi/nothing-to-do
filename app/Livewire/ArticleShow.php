<?php

namespace App\Livewire;

use App\Models\Article;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Reads one Bibliothek article. Published entries are visible to every
 * account; an unpublished draft is visible only to an admin, previewing
 * exactly what a reader would eventually see. See CLAUDE.md, "Bibliothek —
 * Blog, Docs & Leitfäden".
 */
#[Layout('layouts.app')]
class ArticleShow extends Component
{
    public int $articleId;

    /**
     * Article is resolved via Laravel/Livewire's implicit route-model binding
     * (a nonexistent id already 404s here, before this method's body runs) —
     * only its id is kept as a public property, re-resolved fresh through
     * article() below, mirroring ProjectPage's own mount(Project $project)
     * convention.
     */
    public function mount(Article $article): void
    {
        abort_if(! $article->is_published && ! (bool) auth()->user()->is_admin, 404);

        $this->articleId = $article->id;
    }

    #[Computed]
    public function article(): Article
    {
        return Article::findOrFail($this->articleId);
    }

    #[Computed]
    public function contentHtml(): string
    {
        return Article::renderMarkdown($this->article->content);
    }

    public function render()
    {
        return view('livewire.article-show');
    }
}
