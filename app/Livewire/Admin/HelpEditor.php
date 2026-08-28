<?php

namespace App\Livewire\Admin;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Admin-only editor for the Hilfe-Center: manage the category/subcategory
 * tree, and write articles inside it. Gated in mount(), not by route
 * middleware — same convention as AnnouncementEditor. See CLAUDE.md,
 * "Hilfe-Center & Support".
 */
#[Layout('layouts.app')]
class HelpEditor extends Component
{
    /** Null shows the tree; an article's id switches to the full-bleed writing view. */
    public ?int $editingId = null;

    public string $formTitle = '';

    /**
     * Not int-typed on purpose: the <select> syncs it as a string (or '' for
     * "Ohne Kategorie"), and Livewire's model sync doesn't coerce that to
     * int/null before the property is written — updatedFormCategoryId()
     * does that conversion explicitly instead of risking a TypeError.
     */
    public $formCategoryId = null;

    public string $formContent = '';

    public bool $formIsPublished = false;

    public ?string $formPublishedAt = null;

    public bool $showPreview = false;

    // ── Inline category creation/rename (tree view only) ────────────────

    public bool $creatingRootCategory = false;

    public string $newRootCategoryName = '';

    /** Parent category id currently showing its "+ Unterkategorie" input, if any. */
    public ?int $creatingSubcategoryFor = null;

    public string $newSubcategoryName = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->is_admin, 403);
    }

    /** @return Collection<int, HelpCategory> */
    #[Computed]
    public function tree(): Collection
    {
        return HelpCategory::tree(publishedOnly: false);
    }

    /** @return Collection<int, HelpArticle> */
    #[Computed]
    public function uncategorizedArticles(): Collection
    {
        return HelpArticle::whereNull('help_category_id')->orderBy('sort_order')->get();
    }

    /** Flat "Kategorie" / "Kategorie / Unterkategorie" options for the editor's category picker. */
    #[Computed]
    public function categoryOptions(): array
    {
        $options = ['' => 'Ohne Kategorie'];

        foreach ($this->tree as $root) {
            $options[$root->id] = $root->name;

            foreach ($root->children as $child) {
                $options[$child->id] = $root->name.' / '.$child->name;
            }
        }

        return $options;
    }

    #[Computed]
    public function previewHtml(): string
    {
        return HelpArticle::renderMarkdown($this->formContent);
    }

    // ── Category tree mutations ──────────────────────────────────────────

    public function startCreatingRootCategory(): void
    {
        $this->creatingRootCategory = true;
        $this->newRootCategoryName = '';
    }

    public function saveRootCategory(): void
    {
        $name = trim($this->newRootCategoryName);

        if ($name !== '') {
            HelpCategory::create([
                'name' => $name,
                'sort_order' => HelpCategory::roots()->count(),
            ]);
        }

        $this->creatingRootCategory = false;
        $this->newRootCategoryName = '';
        unset($this->tree, $this->categoryOptions);
    }

    public function startCreatingSubcategory(int $parentId): void
    {
        $this->creatingSubcategoryFor = $parentId;
        $this->newSubcategoryName = '';
    }

    public function saveSubcategory(): void
    {
        $name = trim($this->newSubcategoryName);
        $parent = $this->creatingSubcategoryFor !== null ? HelpCategory::find($this->creatingSubcategoryFor) : null;

        if ($name !== '' && $parent !== null) {
            HelpCategory::create([
                'name' => $name,
                'parent_id' => $parent->id,
                'sort_order' => $parent->children()->count(),
            ]);
        }

        $this->creatingSubcategoryFor = null;
        $this->newSubcategoryName = '';
        unset($this->tree, $this->categoryOptions);
    }

    public function cancelCategoryForm(): void
    {
        $this->creatingRootCategory = false;
        $this->creatingSubcategoryFor = null;
    }

    public function renameCategory(int $id, string $name): void
    {
        $name = trim($name);

        if ($name !== '') {
            HelpCategory::whereKey($id)->update(['name' => $name]);
        }

        unset($this->tree, $this->categoryOptions);
    }

    /**
     * Non-destructive: the migration's nullOnDelete drops this category's
     * articles back to "Ohne Kategorie" and any subcategory back to
     * top-level — nothing is lost, mirroring EventCategory's own deletion
     * philosophy.
     */
    public function deleteCategory(int $id): void
    {
        HelpCategory::findOrFail($id)->delete();
        unset($this->tree, $this->categoryOptions, $this->uncategorizedArticles);
    }

    // ── Article editor ───────────────────────────────────────────────────

    /**
     * Creates a blank draft immediately and opens it — mirrors ProjectPage's
     * "empty projects open straight into the editor" shape.
     */
    public function createArticle(?int $categoryId): void
    {
        $article = auth()->user()->createdHelpArticles()->create([
            'title' => 'Neuer Artikel',
            'help_category_id' => $categoryId,
            'content' => null,
            'is_published' => false,
            'sort_order' => $categoryId !== null
                ? HelpArticle::where('help_category_id', $categoryId)->count()
                : HelpArticle::whereNull('help_category_id')->count(),
        ]);

        $this->openEditor($article);
    }

    public function startEdit(int $id): void
    {
        $this->openEditor(HelpArticle::findOrFail($id));
    }

    private function openEditor(HelpArticle $article): void
    {
        $this->editingId = $article->id;
        $this->formTitle = $article->title;
        $this->formCategoryId = $article->help_category_id;
        $this->formContent = (string) ($article->content ?? '');
        $this->formIsPublished = $article->is_published;
        $this->formPublishedAt = $article->published_at?->isoFormat('D.M.YYYY, HH:mm');
        $this->showPreview = false;
    }

    public function backToList(): void
    {
        $this->editingId = null;
        unset($this->tree, $this->uncategorizedArticles);
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

    /** Fires when the category <select> changes — formCategoryId is bound via wire:model.live. */
    public function updatedFormCategoryId(): void
    {
        $id = $this->formCategoryId !== '' && $this->formCategoryId !== null ? (int) $this->formCategoryId : null;

        if ($id !== null && ! array_key_exists($id, $this->categoryOptions)) {
            $id = null;
        }

        $this->formCategoryId = $id;
        $this->persist(['help_category_id' => $id]);
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

        HelpArticle::whereKey($this->editingId)->update($attributes);
    }

    public function togglePublish(): void
    {
        if ($this->editingId === null) {
            return;
        }

        $article = HelpArticle::findOrFail($this->editingId);
        $article->togglePublished();

        $this->formIsPublished = $article->is_published;
        $this->formPublishedAt = $article->published_at?->isoFormat('D.M.YYYY, HH:mm');
    }

    public function deleteArticle(int $id): void
    {
        HelpArticle::findOrFail($id)->delete();

        if ($this->editingId === $id) {
            $this->editingId = null;
        }

        unset($this->tree, $this->uncategorizedArticles);
    }

    public function render()
    {
        return view('livewire.admin.help-editor');
    }
}
