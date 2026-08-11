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
    public const TARGETS = ['inbox', 'todos', 'tasks', 'project', 'craft', 'agenda'];

    /** Targets that create a Task — the value doubles as the `list` column. */
    public const TASK_TARGETS = ['inbox', 'todos', 'tasks'];

    public string $title = '';

    public string $target = 'inbox';

    public ?string $deadline = null;

    public ?string $dueDate = null;

    public string $notes = '';

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

    /** The classes this user can file an agenda entry into. Empty for most users. */
    #[Computed]
    public function agendaSpaces(): Collection
    {
        return auth()->user()->agendaSpaces()->ordered()->get();
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
        // along invisibly and written on the next save.
        if (! in_array($target, self::TASK_TARGETS, true)) {
            $this->dueDate = null;
            $this->notes = '';
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
    public function resetPanel(?string $target = null): void
    {
        $this->reset(['title', 'target', 'deadline', 'dueDate', 'notes', 'whereToBegin', 'captured', 'agendaType', 'subject', 'date', 'agendaSpaceId']);
        $this->resetValidation();

        if ($target !== null && in_array($target, self::TARGETS, true)) {
            $this->target = $target;
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

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'target' => ['required', Rule::in(self::TARGETS)],
            'deadline' => ['nullable', 'date'],
            'dueDate' => ['nullable', 'date'],
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

        $this->validate($rules);

        $user = auth()->user();
        $title = $this->title;
        $notes = trim($this->notes);

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
                'agenda_space_id' => $this->agendaSpaceId,
            ]),
            default => $user->tasks()->create([
                'title' => $title,
                'list' => $this->target,
                'deadline' => $this->deadline ?: null,
                'due_date' => $this->dueDate ?: null,
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
        $this->reset(['title', 'deadline', 'dueDate', 'notes', 'whereToBegin']);

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
        ];
    }

    public function render()
    {
        return view('livewire.quick-capture');
    }
}
