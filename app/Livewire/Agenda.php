<?php

namespace App\Livewire;

use App\Models\AgendaEntry;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
class Agenda extends Component
{
    /** 'all' | 'homework' | 'exam' */
    public string $filterType = 'all';

    public bool $showForm = false;

    /** Null while creating; the entry's id while editing. */
    public ?int $editingId = null;

    public string $formType = 'homework';

    public string $formSubject = '';

    public string $formTitle = '';

    public string $formDate = '';

    public string $formNotes = '';

    /**
     * Always re-resolve through the visibility scope — never trust a frontend id
     * alone. Since entries became shareable this is no longer "mine": it is
     * "mine, or my class's". Everything outside that (another user's private
     * entry, a space I never joined) 404s, and every member may edit what their
     * class posted, which is the agreed rule.
     */
    private function visibleEntry(int $id): AgendaEntry
    {
        return AgendaEntry::query()
            ->visibleTo(auth()->user())
            ->withCompletionState(auth()->user())
            ->findOrFail($id);
    }

    /** @return Collection<int, AgendaEntry> */
    #[Computed]
    public function openEntries(): Collection
    {
        return $this->baseQuery()->openFor(auth()->user())->ordered()->get();
    }

    /** @return Collection<int, AgendaEntry> */
    #[Computed]
    public function doneEntries(): Collection
    {
        return $this->baseQuery()->doneFor(auth()->user())->ordered()->get();
    }

    /** Distinct subjects already used, for the Fach combobox suggestions. */
    #[Computed]
    public function existingSubjects(): Collection
    {
        return auth()->user()->agendaEntries()
            ->select('subject')
            ->distinct()
            ->orderBy('subject')
            ->pluck('subject');
    }

    private function baseQuery()
    {
        $user = auth()->user();

        $query = AgendaEntry::query()->visibleTo($user)->withCompletionState($user);

        if ($this->filterType !== 'all') {
            $query->ofType($this->filterType);
        }

        return $query;
    }

    // ── Form ──────────────────────────────────────────────────────────

    public function setFilter(string $type): void
    {
        $this->filterType = in_array($type, ['all', 'homework', 'exam'], true) ? $type : 'all';
    }

    public function openCreateForm(): void
    {
        $this->editingId = null;
        $this->formType = 'homework';
        $this->formSubject = '';
        $this->formTitle = '';
        $this->formDate = '';
        $this->formNotes = '';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function startEdit(int $id): void
    {
        $entry = $this->visibleEntry($id);

        $this->editingId = $entry->id;
        $this->formType = $entry->type;
        $this->formSubject = $entry->subject;
        $this->formTitle = $entry->title;
        $this->formDate = $entry->date->toDateString();
        $this->formNotes = (string) ($entry->notes ?? '');
        $this->resetValidation();
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function saveEntry(): void
    {
        $this->formSubject = trim($this->formSubject);
        $this->formTitle = trim($this->formTitle);

        $data = $this->validate([
            'formType' => ['required', 'in:'.implode(',', array_keys(AgendaEntry::TYPES))],
            'formSubject' => ['required', 'string', 'max:100'],
            'formTitle' => ['required', 'string', 'max:255'],
            'formDate' => ['required', 'date'],
            'formNotes' => ['nullable', 'string', 'max:2000'],
        ]);

        $attributes = [
            'type' => $data['formType'],
            'subject' => $data['formSubject'],
            'title' => $data['formTitle'],
            'date' => $data['formDate'],
            'notes' => trim($data['formNotes'] ?? '') !== '' ? trim($data['formNotes']) : null,
        ];

        if ($this->editingId !== null) {
            $this->visibleEntry($this->editingId)->update($attributes);
        } else {
            auth()->user()->agendaEntries()->create($attributes);
        }

        $this->showForm = false;
        $this->editingId = null;
    }

    /**
     * Ticks the entry off for *this* person only. On a shared class entry every
     * member has their own completion — nobody can clear someone else's list.
     */
    public function toggleDone(int $id): void
    {
        $this->visibleEntry($id)->toggleDoneFor(auth()->user());
    }

    public function deleteEntry(int $id): void
    {
        $this->visibleEntry($id)->delete();

        if ($this->editingId === $id) {
            $this->cancelForm();
        }
    }

    /**
     * An entry captured through the app-wide QuickCapture panel has to appear
     * here right away — that panel is a separate component, so its write
     * doesn't re-render this one on its own.
     */
    #[On('captured')]
    public function refreshEntries(): void
    {
        // Handling the event is the re-render; every read is a computed property.
    }

    public function render()
    {
        return view('livewire.agenda');
    }
}
