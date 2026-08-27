<?php

namespace App\Livewire\Admin;

use App\Models\Article;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin-only editor for App\Models\Article — the writing side of the
 * Bibliothek (Blog/Doc/Leitfaden), read by every account in
 * App\Livewire\Library / App\Livewire\ArticleShow. Gated in mount(), not by
 * route middleware — same convention as AnnouncementEditor. See CLAUDE.md,
 * "Bibliothek — Blog, Docs & Leitfäden".
 */
#[Layout('layouts.app')]
class ArticleEditor extends Component
{
    /** Null shows the list; an article's id switches to the full-bleed writing view. */
    public ?int $editingId = null;

    public string $formTitle = '';

    /** A key into Article::TYPES. */
    public string $formType = Article::DEFAULT_TYPE;

    public string $formContent = '';

    public bool $formIsPublished = false;

    public ?string $formPublishedAt = null;

    public bool $showPreview = false;

    public function mount(): void
    {
        abort_unless(auth()->user()->is_admin, 403);
    }

    /** Every article, draft and published alike — an admin needs to see both. */
    #[Computed]
    public function articles(): Collection
    {
        return Article::query()->with('author')->newestFirst()->get();
    }

    #[Computed]
    public function typeOptions(): array
    {
        return Article::TYPES;
    }

    /**
     * Rendered from the current (unsaved-to-this-request) form field —
     * formContent is itself already autosaved on every change (see
     * updatedFormContent()), so unlike AnnouncementEditor's preview this
     * never needs an explicit "Vorschau aktualisieren" refresh: opening the
     * toggle already shows the latest content.
     */
    #[Computed]
    public function previewHtml(): string
    {
        return Article::renderMarkdown($this->formContent);
    }

    /**
     * Creates a blank draft immediately and opens it — mirrors ProjectPage's
     * "empty projects open straight into the editor" shape. There is no
     * unsaved-draft limbo: the record exists in the database as soon as the
     * writing view opens, so every keystroke from here on has something to
     * autosave onto.
     */
    public function createArticle(): void
    {
        $article = auth()->user()->createdArticles()->create([
            'title' => 'Neuer Artikel',
            'type' => Article::DEFAULT_TYPE,
            'content' => null,
            'is_published' => false,
        ]);

        $this->openEditor($article);
        unset($this->articles);
    }

    public function startEdit(int $id): void
    {
        $this->openEditor(Article::findOrFail($id));
    }

    private function openEditor(Article $article): void
    {
        $this->editingId = $article->id;
        $this->formTitle = $article->title;
        $this->formType = $article->type;
        $this->formContent = (string) ($article->content ?? '');
        $this->formIsPublished = $article->is_published;
        $this->formPublishedAt = $article->published_at?->isoFormat('D.M.YYYY, HH:mm');
        $this->showPreview = false;
    }

    public function backToList(): void
    {
        $this->editingId = null;
        unset($this->articles);
    }

    /** Autosaves on every change — no explicit "Speichern" button, this is a continuous writing surface. */
    public function updatedFormTitle(): void
    {
        $this->persist(['title' => trim($this->formTitle)]);
    }

    public function updatedFormContent(): void
    {
        $this->persist(['content' => trim($this->formContent) !== '' ? $this->formContent : null]);
    }

    public function setType(string $type): void
    {
        if (! array_key_exists($type, Article::TYPES)) {
            return;
        }

        $this->formType = $type;
        $this->persist(['type' => $type]);
    }

    public function togglePreview(): void
    {
        $this->showPreview = ! $this->showPreview;
    }

    private function persist(array $attributes): void
    {
        if ($this->editingId === null) {
            return;
        }

        Article::whereKey($this->editingId)->update($attributes);
    }

    public function togglePublish(): void
    {
        if ($this->editingId === null) {
            return;
        }

        $article = Article::findOrFail($this->editingId);
        $article->togglePublished();

        $this->formIsPublished = $article->is_published;
        $this->formPublishedAt = $article->published_at?->isoFormat('D.M.YYYY, HH:mm');
        unset($this->articles);
    }

    public function deleteArticle(int $id): void
    {
        Article::findOrFail($id)->delete();

        if ($this->editingId === $id) {
            $this->editingId = null;
        }

        unset($this->articles);
    }

    public function render()
    {
        return view('livewire.admin.article-editor');
    }
}
