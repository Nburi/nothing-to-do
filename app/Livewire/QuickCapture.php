<?php

namespace App\Livewire;

use Illuminate\Validation\Rule;
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
    public const TARGETS = ['inbox', 'todos', 'tasks', 'project', 'craft'];

    /** Targets that create a Task — the value doubles as the `list` column. */
    public const TASK_TARGETS = ['inbox', 'todos', 'tasks'];

    public string $title = '';

    public string $target = 'inbox';

    public ?string $deadline = null;

    public ?string $dueDate = null;

    public string $whereToBegin = '';

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
            default => 'Inbox',
        };
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
        }

        if ($target !== 'craft') {
            $this->whereToBegin = '';
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
        $this->reset(['title', 'target', 'deadline', 'dueDate', 'whereToBegin', 'captured']);
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

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'target' => ['required', Rule::in(self::TARGETS)],
            'deadline' => ['nullable', 'date'],
            'dueDate' => ['nullable', 'date'],
            'whereToBegin' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = auth()->user();
        $title = $this->title;

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
            default => $user->tasks()->create([
                'title' => $title,
                'list' => $this->target,
                'deadline' => $this->deadline ?: null,
                'due_date' => $this->dueDate ?: null,
                'sort_order' => 0,
            ]),
        };

        $this->captured = ['title' => $title, 'label' => self::labelFor($this->target)];

        // The target deliberately survives: capturing three To-Dos in a row
        // shouldn't mean re-picking the chip every time.
        $this->reset(['title', 'deadline', 'dueDate', 'whereToBegin']);

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
            'whereToBegin.max' => 'Das ist zu lang — höchstens 2000 Zeichen.',
        ];
    }

    public function render()
    {
        return view('livewire.quick-capture');
    }
}
