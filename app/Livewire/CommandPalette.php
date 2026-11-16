<?php

namespace App\Livewire;

use App\Services\PaletteSearch;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The command palette (Strg/⌘+K, or "/"): one search box that jumps to any
 * page, task, project, group, agenda entry, Bastelidee or help article, and
 * can hand a typed sentence straight to the capture panel. Mounted once in
 * layouts/app.blade.php (like QuickCapture) so it works from every page.
 * Open/closed and the keyboard cursor are Alpine state (the `commandPalette`
 * store in app.js) — only the search itself round-trips.
 */
class CommandPalette extends Component
{
    public string $query = '';

    /** Every session starts from an empty box, not the last one's leftovers. */
    #[On('command-palette-opened')]
    public function resetPalette(): void
    {
        $this->reset('query');
    }

    /**
     * @return list<array{key: string, label: string, items: list<array{title: string, subtitle: string, href: string, icon: string}>}>
     */
    #[Computed]
    public function groups(): array
    {
        return PaletteSearch::search(auth()->user(), $this->query);
    }

    public function updatedQuery(): void
    {
        // The client's keyboard cursor points at an index in the *previous*
        // result list — tell it to go back to the first row of the new one.
        $this->dispatch('command-palette-results');
    }

    public function render()
    {
        return view('livewire.command-palette');
    }
}
