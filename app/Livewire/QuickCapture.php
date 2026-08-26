<?php

namespace App\Livewire;

use App\Models\AgendaEntry;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The app-wide capture panel. Lives in the layout (not in TaskBoard) so it can
 * be opened from any page — that reach is the whole point of replacing the
 * board's three inline forms with one panel.
 *
 * Open/closed is *not* server state: it's ephemeral UI, so it lives in the
 * Alpine `quickCapture` store (see resources/js/app.js), exactly like the
 * project picker and the draw state. This component only owns what gets
 * written to the database.
 */
class QuickCapture extends Component
{
    /**
     * Capture targets, in the order the chip row renders them. The first three
     * are also the task `list` value they write; the last two go to entirely
     * different tables, which is why this isn't just Task::BOARD_LISTS.
     */
    public const TARGETS = ['inbox', 'todos', 'tasks', 'group', 'project', 'craft', 'agenda'];

    /** Targets that create a Task — the value doubles as the `list` column. */
    public const TASK_TARGETS = ['inbox', 'todos', 'tasks'];

    public string $title = '';

    public string $target = 'inbox';

    public ?string $deadline = null;

    public ?string $dueDate = null;

    public string $notes = '';

    /**
     * Estimated duration in minutes, for the Planer's own math — never
     * required, mirrors the card-face quick-set field. Relevant for the same
     * targets that write a Task with a real list (todos/tasks/group — never
     * Inbox, which is out of scope for planning), plus Agenda homework
     * (never exams, which the Planer never touches either).
     */
    public ?int $duration = null;

    public string $whereToBegin = '';

    /**
     * Agenda fields. Unlike every other target's extras these are *required*,
     * not optional — an AgendaEntry has no meaning without a subject and a date
     * (see Agenda::save()). The panel mirrors that page's rules rather than
     * inventing looser ones.
     */
    public string $agendaType = 'homework';

    public string $subject = '';

    public ?string $date = null;

    /** Null = "nur ich"; a space id = the whole class sees it. Agenda target only. */
    public ?int $agendaSpaceId = null;

    /**
     * Group target. Either an existing group id, or null plus a name in
     * $newGroupName — which is how a group gets created from the keyboard at
     * all, since a group with no first task would be an empty shell.
     */
    public ?int $groupId = null;

    public string $newGroupName = '';

    /** Which of the group's own lists the task lands in. */
    public string $groupList = 'inbox';

    /**
     * The thing captured last, echoed back as a confirmation line so the panel
     * can stay open for the next entry without leaving any doubt that the
     * previous one landed. Null until something has been captured.
     *
     * @var array{title: string, label: string}|null
     */
    public ?array $captured = null;

    /** Human label per target — used for the chips and the confirmation line. */
    public static function labelFor(string $target): string
    {
        return match ($target) {
            'todos' => 'To-Do',
            'tasks' => 'Task',
            'group' => 'Gruppe',
            'project' => 'Projekt',
            'craft' => 'Bastelidee',
            'agenda' => 'Agenda',
            default => 'Inbox',
        };
    }

    /** Distinct subjects already used — same suggestion source as the Agenda page's Fach field. */
    #[Computed]
    public function existingSubjects(): Collection
    {
        return AgendaEntry::query()
            ->visibleTo(auth()->user())
            ->select('subject')
            ->distinct()
            ->orderBy('subject')
            ->pluck('subject');
    }

    /** The task groups a captured task can be filed into. */
    #[Computed]
    public function taskGroups(): Collection
    {
        return auth()->user()->taskGroups()->ordered()->get();
    }

    /** The classes this user can file an agenda entry into. Empty for most users. */
    #[Computed]
    public function agendaSpaces(): Collection
    {
        return auth()->user()->agendaSpaces()->ordered()->get();
    }

    /**
     * The "Für" default when the agenda target is freshly selected — same
     * reasoning as Agenda::openCreateForm()'s equivalent default: which class
     * is meant is only unambiguous when there's exactly one. Two or more stays
     * private, same as before.
     */
    private function defaultAgendaSpaceId(): ?int
    {
        return $this->agendaSpaces->count() === 1 ? $this->agendaSpaces->first()->id : null;
    }

    /** The notes buffer rendered to safe HTML for the panel's preview (task targets only). */
    #[Computed]
    public function notesHtml(): string
    {
        return Task::renderNotesMarkdown($this->notes);
    }

    public function setTarget(string $target): void
    {
        if (! in_array($target, self::TARGETS, true)) {
            return; // a target that isn't ours is simply ignored, never trusted
        }

        $this->target = $target;

        // Fields that don't exist for the new target would otherwise be carried
        // along invisibly and written on the next save. A group capture writes a
        // task, so it keeps the task fields.
        if (! in_array($target, [...self::TASK_TARGETS, 'group'], true)) {
            $this->dueDate = null;
            $this->notes = '';
        }

        // Duration is narrower than deadline/notes above: Inbox writes a Task
        // too, but it's out of the Planer's scope (untriaged), so it doesn't
        // get asked for one either — same rule as the card-face ghost.
        if (! in_array($target, ['todos', 'tasks', 'group'], true)) {
            $this->duration = null;
        }

        if ($target !== 'group') {
            $this->groupId = null;
            $this->newGroupName = '';
            $this->groupList = 'inbox';
        }

        if ($target !== 'craft') {
            $this->whereToBegin = '';
        } else {
            $this->deadline = null;
        }

        if ($target !== 'agenda') {
            $this->subject = '';
            $this->date = null;
            $this->agendaType = 'homework';
            $this->agendaSpaceId = null;
        } else {
            $this->deadline = null;
            $this->agendaSpaceId = $this->defaultAgendaSpaceId();
        }

        $this->resetValidation();
    }

    /**
     * Clears everything, including the confirmation line. Fired by the Alpine
     * store the moment the panel opens, so every session starts clean rather
     * than showing the last one's leftovers.
     *
     * `$target` lets the trigger open the panel on a chip other than Inbox —
     * a page about one specific kind of thing should capture that thing. An
     * unknown value falls back to the Inbox default rather than being trusted.
     */
    #[On('quick-capture-opened')]
    public function resetPanel(?string $target = null, ?int $groupId = null): void
    {
        $this->reset(['title', 'target', 'deadline', 'dueDate', 'duration', 'notes', 'whereToBegin', 'captured', 'agendaType', 'subject', 'date', 'agendaSpaceId', 'groupId', 'newGroupName', 'groupList']);
        $this->resetValidation();

        if ($target !== null && in_array($target, self::TARGETS, true)) {
            $this->target = $target;
        }

        // Opened straight onto the agenda target (e.g. Agenda's own page-matching
        // preselect) gets the same single-class default as setTarget() below.
        if ($this->target === 'agenda') {
            $this->agendaSpaceId = $this->defaultAgendaSpaceId();
        }

        // Opened from inside a group: preselect it, so the panel doesn't ask for
        // a name for a new group while standing in an existing one. Checked
        // against the user's own groups — the id comes from the page, but it is
        // still an id arriving from the client.
        if ($groupId !== null && $this->taskGroups->contains('id', $groupId)) {
            $this->groupId = $groupId;
        }
    }

    /**
     * Writes the entry and keeps the panel open with an empty title, so several
     * things can be dumped in a row — the same way the old inline bars stayed
     * put after adding. Closing is always an explicit Escape / click-outside.
     */
    public function save(): void
    {
        $this->title = trim($this->title);
        $this->whereToBegin = trim($this->whereToBegin);
        $this->subject = trim($this->subject);
        $this->newGroupName = trim($this->newGroupName);

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'target' => ['required', Rule::in(self::TARGETS)],
            'deadline' => ['nullable', 'date'],
            'dueDate' => ['nullable', 'date'],
            'duration' => ['nullable', 'integer', 'min:1', 'max:600'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'whereToBegin' => ['nullable', 'string', 'max:2000'],
        ];

        // Agenda's extras are required, so they're only enforced when that's the
        // chosen target — otherwise every other capture would demand a Fach.
        if ($this->target === 'agenda') {
            $rules['agendaType'] = ['required', Rule::in(array_keys(AgendaEntry::TYPES))];
            $rules['subject'] = ['required', 'string', 'max:100'];
            $rules['date'] = ['required', 'date'];
            // Same authorization boundary as Agenda::saveEntry(): a space id is
            // only acceptable if it's one this user actually belongs to.
            $rules['agendaSpaceId'] = ['nullable', 'integer', Rule::in($this->agendaSpaces->pluck('id'))];
        }

        // A group capture needs a group: either an existing one, or a name for a
        // new one. Requiring one of the two is what keeps empty shells from
        // being created — a group's reason to exist is the tasks in it.
        if ($this->target === 'group') {
            $rules['groupId'] = ['nullable', 'integer', Rule::in($this->taskGroups->pluck('id'))];
            $rules['newGroupName'] = [$this->groupId === null ? 'required' : 'nullable', 'string', 'max:255'];
            $rules['groupList'] = ['required', Rule::in(Task::BOARD_LISTS)];
        }

        $this->validate($rules);

        $user = auth()->user();
        $title = $this->title;
        $notes = trim($this->notes);

        if ($this->target === 'group') {
            $group = $this->groupId !== null
                ? $this->taskGroups->firstWhere('id', $this->groupId)
                : $user->taskGroups()->create(['name' => $this->newGroupName, 'sort_order' => 0]);

            $user->tasks()->create([
                'title' => $title,
                'list' => $this->groupList,
                'group_id' => $group->id,
                'deadline' => $this->deadline ?: null,
                'due_date' => $this->dueDate ?: null,
                'duration_minutes' => $this->duration ?: null,
                'notes' => $notes !== '' ? $notes : null,
                'sort_order' => 0,
            ]);

            // A group created here becomes the selected one, so the next capture
            // lands in it too — writing down three steps of one thing in a row
            // is the normal case, exactly like the Agenda's Fach.
            $this->groupId = $group->id;
            $this->newGroupName = '';

            $this->captured = ['title' => $title, 'label' => 'Gruppe · '.$group->name];
            $this->reset(['title', 'deadline', 'dueDate', 'duration', 'notes', 'whereToBegin']);
            $this->dispatch('captured');

            return;
        }

        match ($this->target) {
            'project' => $user->projects()->create([
                'name' => $title,
                'deadline' => $this->deadline ?: null,
                'sort_order' => 0,
            ]),
            'craft' => $user->craftIdeas()->create([
                'title' => $title,
                'where_to_begin' => $this->whereToBegin !== '' ? $this->whereToBegin : null,
            ]),
            'agenda' => $user->agendaEntries()->create([
                'type' => $this->agendaType,
                'subject' => $this->subject,
                'title' => $title,
                'date' => $this->date,
                // Only meaningful for homework — the field isn't even shown for an
                // exam, but guard explicitly the same way Agenda::saveEntry() does.
                'duration_minutes' => $this->agendaType === 'homework' ? ($this->duration ?: null) : null,
                'agenda_space_id' => $this->agendaSpaceId,
            ]),
            default => $user->tasks()->create([
                'title' => $title,
                'list' => $this->target,
                'deadline' => $this->deadline ?: null,
                'due_date' => $this->dueDate ?: null,
                // Re-checked here, not just relied on from setTarget()'s clearing —
                // this 'default' arm also covers Inbox, which never gets a duration
                // (out of the Planer's scope), so a stale/crafted value must never
                // slip through just because the property happened to hold one.
                'duration_minutes' => in_array($this->target, ['todos', 'tasks', 'group'], true) ? ($this->duration ?: null) : null,
                'notes' => $notes !== '' ? $notes : null,
                'sort_order' => 0,
            ]),
        };

        // Name the class in the confirmation when one was chosen. Sharing is the
        // one capture here with a consequence beyond your own list, so "→ Agenda"
        // alone would hide the part worth double-checking.
        $label = self::labelFor($this->target);

        if ($this->target === 'agenda' && $this->agendaSpaceId !== null) {
            $label .= ' · '.$this->agendaSpaces->firstWhere('id', $this->agendaSpaceId)?->name;
        }

        $this->captured = ['title' => $title, 'label' => $label];

        // The target deliberately survives: capturing three To-Dos in a row
        // shouldn't mean re-picking the chip every time. Agenda's Fach, date,
        // type and class survive for the same reason — writing down three things
        // the teacher just set, all for the same class, is the normal case.
        $this->reset(['title', 'deadline', 'dueDate', 'duration', 'notes', 'whereToBegin']);

        $this->dispatch('captured');
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'title.required' => 'Ohne Titel geht es nicht.',
            'title.max' => 'Höchstens 255 Zeichen.',
            'deadline.date' => 'Das ist kein gültiges Datum.',
            'dueDate.date' => 'Das ist kein gültiges Datum.',
            'notes.max' => 'Das ist zu lang — höchstens 5000 Zeichen.',
            'whereToBegin.max' => 'Das ist zu lang — höchstens 2000 Zeichen.',
            'subject.required' => 'Für die Agenda fehlt noch das Fach.',
            'subject.max' => 'Das Fach ist zu lang — höchstens 100 Zeichen.',
            'date.required' => 'Für die Agenda fehlt noch das Datum.',
            'date.date' => 'Das ist kein gültiges Datum.',
            'newGroupName.required' => 'Wähle eine Gruppe oder gib einer neuen einen Namen.',
            'newGroupName.max' => 'Der Gruppenname ist zu lang — höchstens 255 Zeichen.',
        ];
    }

    public function render()
    {
        return view('livewire.quick-capture');
    }
}
