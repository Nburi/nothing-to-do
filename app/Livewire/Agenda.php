<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesAgendaSpaces;
use App\Models\AgendaDraft;
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

    /** This user's own note on the entry — only ever meaningful when formSpaceId is set; see saveEntry(). */
    public string $formPrivateNotes = '';

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
            ->withPrivateNoteFor(auth()->user())
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

    /** Everyone else's active draft across every space this user belongs to. */
    #[Computed]
    public function activeDrafts(): Collection
    {
        $spaceIds = $this->spaces->pluck('id');

        if ($spaceIds->isEmpty()) {
            return collect();
        }

        return AgendaDraft::query()
            ->whereIn('agenda_space_id', $spaceIds)
            ->excluding(auth()->user())
            ->active()
            ->with(['user:id,name', 'entry:id,title'])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * One ready-to-render line per space that currently has someone active,
     * respecting the current space filter — a class you've filtered away isn't
     * something you need to hear about right now. Grouped rather than one row
     * per person, so three classmates drafting at once doesn't triple the banner.
     *
     * @return Collection<int, array{space: \App\Models\AgendaSpace, text: string}>
     */
    #[Computed]
    public function draftLines(): Collection
    {
        if ($this->filterSpace === 'mine') {
            return collect(); // the private view has nothing shared to report
        }

        $drafts = $this->activeDrafts;

        if ($this->filterSpace !== 'all') {
            $drafts = $drafts->where('agenda_space_id', (int) $this->filterSpace);
        }

        return $drafts
            ->groupBy('agenda_space_id')
            ->map(function (Collection $group) {
                $space = $this->spaces->firstWhere('id', $group->first()->agenda_space_id);

                return $space === null ? null : ['space' => $space, 'text' => $this->describeDrafters($group)];
            })
            ->filter()
            ->values();
    }

    /** "Lisa erstellt gerade eine Hausaufgabe zu Mathematik" and its variants. */
    private function describeDrafters(Collection $drafts): string
    {
        if ($drafts->count() === 1) {
            $draft = $drafts->first();
            $name = $draft->user->name;

            if ($draft->agenda_entry_id !== null) {
                // Editing already names a concrete, existing entry — strictly
                // more specific than type + subject would be, so the type is
                // left out here on purpose (unlike the "creating" branch below,
                // where type + subject is *all* there is to go on).
                return $draft->entry !== null
                    ? "{$name} bearbeitet gerade „{$draft->entry->title}\u{201c}"
                    : "{$name} bearbeitet gerade einen Eintrag";
            }

            // Type matters here: a Hausaufgabe and a Prüfung for the same Fach
            // are not the duplicate the whole feature exists to catch. Direct
            // lookup, no fallback — AgendaDraft::syncFor() never persists a
            // type outside AgendaEntry::TYPES.
            $typeLabel = AgendaEntry::TYPES[$draft->type];

            return trim($draft->subject) !== ''
                ? "{$name} erstellt gerade eine {$typeLabel} zu {$draft->subject}"
                : "{$name} erstellt gerade eine {$typeLabel}";
        }

        $names = $drafts->pluck('user.name');
        $extra = $names->count() - 2;

        $who = match (true) {
            $names->count() === 2 => $names->implode(' und '),
            $extra === 1 => $names->take(2)->implode(', ').' und eine weitere Person',
            default => $names->take(2)->implode(', ')." und {$extra} weitere Personen",
        };

        return "{$who} sind gerade aktiv";
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
            ->withPrivateNoteFor($user)
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
        $this->formPrivateNotes = '';
        $this->formSpaceId = $this->defaultFormSpaceId();
        $this->resetValidation();
        $this->showForm = true;
        $this->syncDraft();
    }

    /**
     * The "Für" default for a fresh entry.
     *
     * Filtered to one specific class: that class — with "Klasse 4b" on screen,
     * the entry is almost certainly for it (same idea as QuickCapture's target
     * following the page it was opened from). Filtered to "Nur ich": private —
     * asking to see only private entries is itself a signal of intent.
     *
     * "Alle Räume" used to fall back to private unconditionally, on the theory
     * that an unfocused filter shouldn't guess. In practice that made the very
     * first entry a brand-new class member writes silently private, since
     * "Alle Räume" is the view joining a class lands you on — the opposite of
     * why they joined. Belonging to exactly one class removes the guesswork
     * ("which class" has only one possible answer), so that case now defaults
     * to it. Two or more classes is still genuinely ambiguous and stays
     * private, same as before — narrowed to the case that's actually unclear,
     * rather than solved outright. That remaining gap is mitigated, not
     * hidden: the "Für" row always names the current choice, private included
     * (see agenda-entry-form.blade.php).
     */
    private function defaultFormSpaceId(): ?int
    {
        if (is_numeric($this->filterSpace)) {
            return (int) $this->filterSpace;
        }

        if ($this->filterSpace === 'mine') {
            return null;
        }

        return $this->spaces->count() === 1 ? $this->spaces->first()->id : null;
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
        $this->formPrivateNotes = (string) ($entry->privateNoteFor(auth()->user()) ?? '');
        $this->formSpaceId = $entry->agenda_space_id;
        $this->resetValidation();
        $this->showForm = true;
        $this->syncDraft();
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        AgendaDraft::clearFor(auth()->user());
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
            // Only ever surfaced in the form while formSpaceId is set (a
            // private entry has exactly one viewer already), but validated
            // unconditionally in case the "Für" pill changes after typing.
            'formPrivateNotes' => ['nullable', 'string', 'max:2000'],
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
            $entry = $this->visibleEntry($this->editingId);
            $entry->update($attributes);
        } else {
            $entry = auth()->user()->agendaEntries()->create($attributes);
        }

        // A private note is only meaningful once the entry is actually shared —
        // a "Nur ich" entry already has exactly one viewer, so leave any
        // leftover row (from a "Für" switch after typing) untouched rather
        // than guessing whether it should be deleted.
        if ($data['formSpaceId'] !== null) {
            $entry->setPrivateNoteFor(auth()->user(), $data['formPrivateNotes'] ?? null);
        }

        $this->showForm = false;
        $this->editingId = null;
        AgendaDraft::clearFor(auth()->user());
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

    // ── Drafting presence ────────────────────────────────────────────
    //
    // Broadcasts "I'm working on this" to the rest of a shared class while the
    // form is open, so two people don't write the same homework down twice.
    // formType/formSpaceId sync immediately (pill clicks are already a request
    // each); formSubject syncs on a debounce (see the .live.debounce.800ms
    // binding in the form partial) so typing doesn't fire a request per key.

    public function updatedFormType(): void
    {
        $this->syncDraft();
    }

    public function updatedFormSpaceId(): void
    {
        $this->syncDraft();
    }

    public function updatedFormSubject(): void
    {
        $this->syncDraft();
    }

    private function syncDraft(): void
    {
        if (! $this->showForm) {
            return;
        }

        AgendaDraft::syncFor(auth()->user(), $this->formSpaceId, $this->editingId, $this->formType, $this->formSubject);
    }

    /**
     * The open form's own keep-alive, called from the ambient banner's 8s poll
     * (agenda.blade.php) while the form is open — re-syncing with the same
     * values simply refreshes the TTL. Without this, a long pause between
     * keystrokes (re-reading the assignment, say) would let the draft quietly
     * expire for everyone else even though the form is still open right in
     * front of you.
     */
    public function heartbeatDraft(): void
    {
        $this->syncDraft();
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
