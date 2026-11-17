<?php

namespace App\Livewire;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The public, guest-readable mirror of the Hilfe-Center — built so Google
 * can actually index the help content instead of it sitting behind
 * /app/help's `auth` middleware (which robots.txt also disallows crawling
 * under /app anyway). See CLAUDE.md, "Hilfe-Center & Support".
 *
 * Deliberately a separate route/component/view from App\Livewire\Help
 * rather than reusing it for guests too: that component renders inside
 * layouts.app, which assumes an authenticated account throughout (the
 * header's avatar menu, HeaderBadges, QuickCapture, the presence
 * heartbeat…) — making it guest-safe would mean auditing every one of
 * those for a null user, a much larger blast radius than one small,
 * self-contained public page. This component owns its own full-page view
 * (no #[Layout] attribute — see livewire/public-help.blade.php) the same
 * way welcome.blade.php is already a self-contained public page rather
 * than trying to bend an authenticated layout to fit a guest.
 *
 * Slug-keyed (not id-keyed, unlike /app/help/{id}) so the URL itself
 * carries real keywords — see HelpArticle::generateSlug(). The
 * authenticated reader keeps id-based URLs unchanged; it isn't crawled,
 * so there's nothing to gain there and no reason to touch it.
 */
class PublicHelp extends Component
{
    public ?string $slug = null;

    /**
     * $slug comes from the route's optional {slug?} segment — a
     * stale/foreign/unpublished slug is silently ignored (never a broken
     * page load), mirroring how Help::mount() reads its own ?article= id.
     */
    public function mount(?string $slug = null): void
    {
        $this->slug = $slug;
    }

    /** @return Collection<int, HelpCategory> */
    #[Computed]
    public function tree(): Collection
    {
        return HelpCategory::tree(publishedOnly: true);
    }

    /** @return Collection<int, HelpArticle> */
    #[Computed]
    public function uncategorizedArticles(): Collection
    {
        return HelpArticle::published()->whereNull('help_category_id')->orderBy('sort_order')->get();
    }

    #[Computed]
    public function selectedArticle(): ?HelpArticle
    {
        return $this->slug !== null
            ? HelpArticle::published()->where('slug', $this->slug)->first()
            : null;
    }

    #[Computed]
    public function contentHtml(): string
    {
        return $this->selectedArticle !== null
            ? HelpArticle::renderMarkdown($this->selectedArticle->content)
            : '';
    }

    public function pageTitle(): string
    {
        return $this->selectedArticle !== null
            ? $this->selectedArticle->title.' – Hilfe – nothing-to-do'
            : 'Hilfe-Center – nothing-to-do';
    }

    public function pageDescription(): string
    {
        if ($this->selectedArticle !== null) {
            return $this->selectedArticle->preview(30)
                ?? 'Antworten und Anleitungen für nothing-to-do, die Aufgaben-App mit Inbox, To-Dos, Tasks und Projekten.';
        }

        return 'Anleitungen und Antworten rund um nothing-to-do: Aufgaben erfassen, Zeitplan, Fokus-Timer, Projekte und mehr.';
    }

    public function canonicalUrl(): string
    {
        return $this->selectedArticle !== null
            ? url('/hilfe/'.$this->selectedArticle->slug)
            : url('/hilfe');
    }

    /**
     * A plain Article schema for the selected article only — helps Google
     * understand this is real reference content, not marketing copy.
     * Deliberately not attempted for the index (no single "headline" to
     * anchor it to) or the site as a whole (that's a bigger, separate
     * WebSite/Organization schema this pass doesn't need).
     */
    public function jsonLd(): ?string
    {
        $article = $this->selectedArticle;

        if ($article === null) {
            return null;
        }

        return json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $article->title,
            'description' => $this->pageDescription(),
            'datePublished' => $article->published_at?->toAtomString(),
            'dateModified' => $article->updated_at->toAtomString(),
            'inLanguage' => 'de-CH',
            'author' => ['@type' => 'Organization', 'name' => 'nothing-to-do'],
            'publisher' => ['@type' => 'Organization', 'name' => 'nothing-to-do'],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $this->canonicalUrl()],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: null;
    }

    public function render()
    {
        return view('livewire.public-help')
            ->layout('layouts.public', [
                'title' => $this->pageTitle(),
                'description' => $this->pageDescription(),
                'canonical' => $this->canonicalUrl(),
                'ogType' => $this->selectedArticle !== null ? 'article' : 'website',
                'jsonLd' => $this->jsonLd(),
            ]);
    }
}
