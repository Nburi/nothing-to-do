<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesAgendaSpaces;
use App\Models\AgendaEntry;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.app')]
class Agenda extends Component
{
    use ManagesAgendaSpaces;

    /** 'all' | 'homework' | 'exam' */
    public string $filterType = 'all';

    /**
     * Which agenda to show: 'all', 'mine' (private only), or a space id as a
     * string. A string throughout because it comes straight off a wire:click
     * chip and gets compared against one.
     */
    public string $filterSpace = 'all';

    /** 'all' or one exact subject string, matched against `existingSubjects()`. */
    public string $filterSubject = 'all';

    /**
     * Sort by date (false, the default) or group into one section per class.
     * Date wins by default because "what is due next" is the actual question;
     * grouping is there for the times the question is "what does 4b have on".
     */
    public bool $groupBySpace = false;

    public bool $showForm = false;

    /** Null while creating; the entry's id while editing. */
    public ?int $editingId = null;

    public string $formType = 'homework';

    public string $formSubject = '';

    public string $formTitle = '';

    public string $formDate = '';

    public string $formNotes = '';

    /** Null = "nur ich"; a space id = visible to that whole class. */
    public ?int $formSpaceId = null;

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

    /**
     * The open entries grouped into one section per class, private last — the
     * "nach Raum" view. Derived from the same computed list the flat view
     * renders, so switching costs no extra query.
     *
     * @return Collection<int, array{key: string, label: string, meta: ?string, entries: Collection<int, AgendaEntry>}>
     */
    #[Computed]
    public function openGroups(): Collection
    {
        return $this->openEntries
            ->groupBy(fn (AgendaEntry $entry) => $entry->agenda_space_id ?? 0)
            ->map(fn (Collection $entries, $spaceId) => [
                'key' => (string) $spaceId,
                'label' => $spaceId === 0 ? 'Nur ich' : ($entries->first()->space?->name ?? 'Nur ich'),
                'meta' => $spaceId === 0 ? null : $this->memberLabel($entries->first()->space?->members_count ?? 0),
                'entries' => $entries,
            ])
            // Private last: it's the fallback bucket, not the headline.
            ->sortBy(fn (array $group) => $group['key'] === '0' ? "\u{FFFF}" : mb_strtolower($group['label']))
            ->values();
    }

    /** Distinct subjects already used, for the Fach combobox suggestions. */
    #[Computed]
    public function existingSubjects(): Collection
    {
        // Everything visible, not just this user's own entries: in a shared
        // class the Fächer are the class's, so a classmate typing "Französisch"
        // once should autocomplete for everyone.
        return AgendaEntry::query()
            ->visibleTo(auth()->user())
            ->select('subject')
            ->distinct()
            ->orderBy('subject')
            ->pluck('subject');
    }

    private function memberLabel(int $count): string
    {
        return $count.' '.($count === 1 ? 'Mitglied' : 'Mitglieder');
    }

    private function baseQuery()
    {
        $user = auth()->user();

        $query = AgendaEntry::query()
            ->visibleTo($user)
            ->withCompletionState($user)
            // The row shows who wrote a class entry and how many of the class
            // have finished it; both eager-loaded so a long list stays 3 queries.
            ->with(['user:id,name', 'space' => fn ($q) => $q->withCount('members')]);

        if ($this->filterType !== 'all') {
            $query->ofType($this->filterType);
        }

        if ($this->filterSubject !== 'all') {
            $query->where('subject', $this->filterSubject);
        }

        if ($this->filterSpace === 'mine') {
            $query->inSpace(null);
        } elseif ($this->filterSpace !== 'all') {
            // Guarded against a stale chip: a space id that is no longer one of
            // mine falls back to showing everything rather than erroring.
            $spaceId = (int) $this->filterSpace;

            if ($this->spaces->contains('id', $spaceId)) {
                $query->inSpace($spaceId);
            }
        }

        return $query;
    }

    // ── Form ──────────────────────────────────────────────────────────

    public function setFilter(string $type): void
    {
        $this->filterType = in_array($type, ['all', 'homework', 'exam'], true) ? $type : 'all';
    }

    public function setSpaceFilter(string $space): void
    {
        $this->filterSpace = $space === 'all' || $space === 'mine' || $this->spaces->contains('id', (int) $space)
            ? $space
            : 'all';
    }

    public function setSubjectFilter(string $subject): void
    {
        $this->filterSubject = $subject === 'all' || $this->existingSubjects->contains($subject)
            ? $subject
            : 'all';
    }

    public function toggleGrouping(): void
    {
        $this->groupBySpace = ! $this->groupBySpace;
    }

    public function openCreateForm(): void
    {
        $this->editingId = null;
        $this->formType = 'homework';
        // Follow the filter, same reasoning as $formSpaceId below: with "Mathematik"
        // on screen, the entry you're about to write is almost certainly for it.
        $this->formSubject = $this->filterSubject !== 'all' ? $this->filterSubject : '';
        $this->formTitle = '';
        $this->formDate = '';
        $this->formNotes = '';
        // Follow the filter: with "Klasse 4b" on screen, the entry you're about
        // to write is almost certainly for 4b. Same idea as QuickCapture's
        // target following the page you opened it from. Defaults to private
        // whenever the filter isn't pointed at one specific class, so nothing
        // is ever shared by accident.
        $this->formSpaceId = is_numeric($this->filterSpace) ? (int) $this->filterSpace : null;
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
        $this->formSpaceId = $entry->agenda_space_id;
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
            // Sharing into a class you don't belong to is not a validation
            // nicety — it's the whole authorization boundary for writes.
            'formSpaceId' => ['nullable', 'integer', Rule::in($this->spaces->pluck('id'))],
        ], attributes: ['formSpaceId' => 'Klasse']);

        $attributes = [
            'type' => $data['formType'],
            'subject' => $data['formSubject'],
            'title' => $data['formTitle'],
            'date' => $data['formDate'],
            'notes' => trim($data['formNotes'] ?? '') !== '' ? trim($data['formNotes']) : null,
            'agenda_space_id' => $data['formSpaceId'],
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
