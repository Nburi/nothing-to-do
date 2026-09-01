<?php

namespace App\Livewire\Concerns;

use App\Models\Task;
use App\Services\ListConcepts;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * Which board mental model (App\Services\ListConcepts) the user has picked —
 * shared between Settings' own "Listen-Konzept" card and the Onboarding
 * tutorial's Konzept/Kernkonzept-Vertiefung steps, so the pick/read logic
 * lives in exactly one place rather than two copies that could drift (same
 * reasoning as ManagesModuleSettings for module visibility/default page).
 */
trait ManagesListConceptSettings
{
    public string $listConcept = 'three_things';

    public function mountManagesListConceptSettings(): void
    {
        $this->listConcept = ListConcepts::for(auth()->user());
    }

    /**
     * @return list<array{key: string, label: string, description: string, available: bool, current: bool}>
     */
    #[Computed]
    public function listConceptRows(): array
    {
        return ListConcepts::rowsFor(auth()->user());
    }

    /**
     * Shared real-data preview behind every available concept's pill/tab —
     * see ListConcepts::previewTasksFor().
     *
     * @return Collection<int, Task>
     */
    #[Computed]
    public function listConceptPreviewTasks(): Collection
    {
        return ListConcepts::previewTasksFor(auth()->user());
    }

    /** Immediate-save pick, like Startseite — only a currently-available concept can actually be chosen. */
    public function setListConcept(string $key): void
    {
        if (! ListConcepts::isValid($key)) {
            return;
        }

        $this->listConcept = $key;
        auth()->user()->update(['list_concept' => $key]);
    }
}
