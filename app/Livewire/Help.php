<?php

namespace App\Livewire;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Models\SupportRequest;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The Hilfe-Center's reader-facing view — a sidebar of categories/
 * subcategories next to the selected article, open to every account. See
 * CLAUDE.md, "Hilfe-Center & Support".
 */
#[Layout('layouts.app')]
class Help extends Component
{
    public ?int $selectedArticleId = null;

    /** Signature moment: a "Nein" on "War das hilfreich?" opens this note, which becomes a feedback request. */
    public bool $showFollowup = false;

    public string $followupNote = '';

    public bool $justSentFeedback = false;

    /**
     * $article comes from the route's optional {article?} segment — a
     * stale/foreign/unpublished id is silently ignored (never a broken page
     * load), mirroring how Schedule::mount() reads its own ?event= param.
     */
    public function mount(?int $article = null): void
    {
        if ($article !== null) {
            $found = HelpArticle::published()->find($article);
            $this->selectedArticleId = $found?->id;
        }
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
        return $this->selectedArticleId !== null
            ? HelpArticle::published()->find($this->selectedArticleId)
            : null;
    }

    #[Computed]
    public function contentHtml(): string
    {
        return $this->selectedArticle !== null
            ? HelpArticle::renderMarkdown($this->selectedArticle->content)
            : '';
    }

    public function openFollowup(): void
    {
        $this->showFollowup = true;
        $this->justSentFeedback = false;
    }

    /**
     * A "Nein" click becomes a real feedback request the moment the note is
     * sent — no separate trip through the Feedback &amp; Support form.
     */
    public function sendFollowupFeedback(): void
    {
        $article = $this->selectedArticle;

        if ($article === null) {
            return;
        }

        $note = trim($this->followupNote);

        auth()->user()->supportRequests()->create([
            'type' => 'feedback',
            'subject' => 'Feedback zu: '.$article->title,
            'message' => $note !== '' ? $note : 'Hat nicht geholfen (ohne weiteren Kommentar).',
            'status' => SupportRequest::DEFAULT_STATUS,
        ]);

        // showFollowup stays true: the box remains, now rendering the
        // "gespeichert" confirmation instead of the note field (see
        // justSentFeedback in the view) — setting it false here would hide
        // that confirmation the instant it should appear.
        $this->followupNote = '';
        $this->justSentFeedback = true;
    }

    public function render()
    {
        return view('livewire.help');
    }
}
