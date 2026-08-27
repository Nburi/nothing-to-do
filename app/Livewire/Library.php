<?php

namespace App\Livewire;

use App\Models\Article;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The Bibliothek's reader-facing overview — published Blog/Doc/Leitfaden
 * entries, for every account. Read-only: writing happens in
 * App\Livewire\Admin\ArticleEditor. See CLAUDE.md, "Bibliothek — Blog, Docs &
 * Leitfäden".
 */
#[Layout('layouts.app')]
class Library extends Component
{
    public string $search = '';

    /** A key into Article::TYPES, or null for "alle". */
    public ?string $typeFilter = null;

    /** @return Collection<int, Article> */
    #[Computed]
    public function articles(): Collection
    {
        return Article::published()
            ->ofType($this->typeFilter)
            ->search($this->search)
            ->publishedFirst()
            ->get();
    }

    #[Computed]
    public function hasAnyPublished(): bool
    {
        return Article::published()->exists();
    }

    /** Clicking the active chip again clears the filter — same toggle shape as Agenda's class chips. */
    public function setTypeFilter(?string $type): void
    {
        $this->typeFilter = $this->typeFilter === $type ? null : $type;
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'typeFilter']);
    }

    public function render()
    {
        return view('livewire.library');
    }
}
