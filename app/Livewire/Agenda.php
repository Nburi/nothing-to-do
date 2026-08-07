<?php

namespace App\Livewire;

use App\Models\AgendaEntry;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
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

    /** Always re-resolve through the owner relationship — never trust a frontend id alone. */
    private function userEntry(int $id): AgendaEntry
    {
        return auth()->user()->agendaEntries()->findOrFail($id);
    }

    /** @return Collection<int, AgendaEntry> */
    #[Computed]
    public function openEntries(): Collection
    {
        return $this->baseQuery()->open()->ordered()->get();
    }

    /** @return Collection<int, AgendaEntry> */
    #[Computed]
    public function doneEntries(): Collection
    {
        return $this->baseQuery()->where('is_done', true)->ordered()->get();
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
        $query = auth()->user()->agendaEntries();

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
        $entry = $this->userEntry($id);

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
            $this->userEntry($this->editingId)->update($attributes);
        } else {
            auth()->user()->agendaEntries()->create($attributes);
        }

        $this->showForm = false;
        $this->editingId = null;
    }

    public function toggleDone(int $id): void
    {
        $entry = $this->userEntry($id);
        $entry->update(['is_done' => ! $entry->is_done]);
    }

    public function deleteEntry(int $id): void
    {
        $this->userEntry($id)->delete();

        if ($this->editingId === $id) {
            $this->cancelForm();
        }
    }

    public function render()
    {
        return view('livewire.agenda');
    }
}
