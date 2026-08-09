<?php

namespace App\Livewire;

use App\Models\CraftIdea;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class CraftIdeas extends Component
{
    /** The idea currently featured as "Mach doch das". Null once every idea is done. */
    public ?int $heroId = null;

    /** Excluded from the pool on the next shuffle, so it doesn't repeat immediately. */
    public ?int $lastHeroId = null;

    /** Always re-resolve through the owner relationship — never trust a frontend id alone. */
    private function userIdea(int $id): CraftIdea
    {
        return auth()->user()->craftIdeas()->findOrFail($id);
    }

    /**
     * Picks a new random open idea into $heroId. With $excludeCurrent, avoids immediately
     * repeating the idea just shown (unless it's the only open idea left).
     */
    private function rerollHero(bool $excludeCurrent): void
    {
        $pool = auth()->user()->craftIdeas()->open()->get();

        if ($pool->isEmpty()) {
            $this->heroId = null;
            $this->lastHeroId = null;

            return;
        }

        $candidates = $excludeCurrent && $this->heroId !== null
            ? $pool->where('id', '!=', $this->heroId)->values()
            : $pool;

        if ($candidates->isEmpty()) {
            $candidates = $pool;
        }

        $this->lastHeroId = $this->heroId;
        $this->heroId = $candidates->random()->id;
    }

    /** Self-heals $heroId if it now points at a done/deleted/foreign idea (or nothing was picked yet). */
    private function ensureHero(): void
    {
        $stillOpen = $this->heroId !== null
            && auth()->user()->craftIdeas()->open()->whereKey($this->heroId)->exists();

        if (! $stillOpen) {
            $this->rerollHero(false);
        }
    }

    #[Computed]
    public function hero(): ?CraftIdea
    {
        return $this->heroId !== null
            ? auth()->user()->craftIdeas()->open()->find($this->heroId)
            : null;
    }

    /** @return Collection<int, CraftIdea> */
    #[Computed]
    public function otherIdeas(): Collection
    {
        return auth()->user()->craftIdeas()
            ->open()
            ->where('id', '!=', $this->heroId)
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, CraftIdea> */
    #[Computed]
    public function doneIdeas(): Collection
    {
        return auth()->user()->craftIdeas()->done()->orderByDesc('updated_at')->get();
    }

    public function shuffle(): void
    {
        $this->rerollHero(true);
    }

    public function promote(int $id): void
    {
        $idea = auth()->user()->craftIdeas()->open()->findOrFail($id);

        $this->lastHeroId = $this->heroId;
        $this->heroId = $idea->id;
    }

    public function markDone(int $id): void
    {
        $this->userIdea($id)->update(['is_done' => true]);
    }

    public function restoreIdea(int $id): void
    {
        $this->userIdea($id)->update(['is_done' => false]);
    }

    public function deleteIdea(int $id): void
    {
        $this->userIdea($id)->delete();
    }

    public function render()
    {
        $this->ensureHero();

        return view('livewire.craft-ideas');
    }
}
