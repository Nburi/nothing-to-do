<?php

namespace App\Livewire\Shell;

use App\Models\Task;
use App\Models\User;
use App\Services\Planning\CrunchDecisions;
use App\Services\Planning\CrunchOverview;
use App\Services\Planning\CrunchView;
use App\Services\Planning\DayLabel;
use App\Support\UserClock;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Refactor step 4.2: the crunch screen (R5; Niels' picks 2026-11-17: a list with day bars,
 * "Mehr Zeit" = a one-off block this week only). A panel on the right of the Werkbank, a sheet
 * from the bottom in Stapel; one markup, CSS picks.
 *
 * Opened by the board's warning line (event crunch-open), from the session rail or drawer, or by
 * the push link /board?crunch=1. Every decision is undoable (CONVENTIONS 7) and tells the board
 * to render again (crunch-changed). Opening the screen counts as having seen the warning.
 */
class Crunch extends Component
{
    /** Undo entries kept at most. */
    public const UNDO_LIMIT = 5;

    public bool $open = false;

    /** The hard task whose deadline is being moved, inline. */
    public ?int $moving = null;

    public string $moveDate = '';

    public bool $moveSure = false;

    /** The "Mehr Zeit" form is open. */
    public bool $adding = false;

    public string $addDate = '';

    public string $addStart = '';

    public string $addEnd = '';

    public ?int $addCategory = null;

    /**
     * What can be undone, newest last. Locked: the server wrote it, the client cannot change it.
     *
     * @var list<array{label: string, kind: string, id: int, before?: array<string, mixed>}>
     */
    #[Locked]
    public array $undo = [];

    public ?string $notice = null;

    public function mount(): void
    {
        $this->open = request()->boolean('crunch');
    }

    #[Computed]
    public function user(): User
    {
        return Auth::user();
    }

    #[Computed]
    public function view(): CrunchView
    {
        return app(CrunchOverview::class)->for($this->user, CarbonImmutable::now(), seen: true);
    }

    #[On('crunch-open')]
    public function openScreen(): void
    {
        $this->open = true;
        $this->notice = null;
        $this->cancelMove();
        $this->cancelAdd();
        unset($this->view);
    }

    public function close(): void
    {
        $this->open = false;
        $this->undo = [];
        $this->notice = null;
        $this->cancelMove();
        $this->cancelAdd();
    }

    // ---- decisions ---------------------------------------------------------------------------

    public function shrink(int $taskId): void
    {
        $this->onTask($taskId, function (Task $task, CrunchDecisions $decisions) {
            $before = CrunchDecisions::snapshot($task);
            $smaller = CrunchDecisions::smaller($task);
            if ($smaller === null) {
                $this->notice = __('shell.cannot_shrink');

                return;
            }
            $decisions->shrink($this->user, $task->id);
            $this->remember(__('shell.crunch.done.smaller', ['task' => $task->title, 'size' => __('shell.sizes.'.$smaller->value)]), 'task', $task->id, $before);
        });
    }

    public function moveSoft(int $taskId): void
    {
        $this->onTask($taskId, function (Task $task, CrunchDecisions $decisions) {
            $before = CrunchDecisions::snapshot($task);
            $moved = $decisions->moveSoft($this->user, $task->id);
            $date = $moved->softDeadline()?->toDateString();
            $label = $date === null
                ? __('shell.crunch.done.soft_dropped', ['task' => $task->title])
                : __('shell.crunch.done.moved', ['task' => $task->title, 'date' => $this->dayLabel($date)]);
            $this->remember($label, 'task', $task->id, $before);
        });
    }

    public function startMove(int $taskId): void
    {
        $this->cancelAdd();
        $this->resetErrorBag();
        $task = Task::forUser($this->user)->active()->find($taskId);
        if ($task?->hardDeadline() === null) {
            $this->refreshGone();

            return;
        }
        $this->moving = $task->id;
        $this->moveDate = CarbonImmutable::parse($task->hardDeadline()->toDateString())->addWeek()->toDateString();
        $this->moveSure = false;
    }

    public function cancelMove(): void
    {
        $this->moving = null;
        $this->moveDate = '';
        $this->moveSure = false;
        $this->resetErrorBag(['moveDate', 'moveSure']);
    }

    public function moveHard(): void
    {
        $this->resetErrorBag(['moveDate', 'moveSure']);
        if ($this->moving === null) {
            return;
        }
        if (! $this->moveSure) {
            $this->addError('moveSure', __('shell.crunch.errors.sure'));

            return;
        }

        $this->onTask($this->moving, function (Task $task, CrunchDecisions $decisions) {
            $before = CrunchDecisions::snapshot($task);
            $decisions->moveHard($this->user, $task->id, $this->moveDate); // a ValidationException shows under the field
            $this->remember(__('shell.crunch.done.moved', ['task' => $task->title, 'date' => $this->dayLabel($this->moveDate)]), 'task', $task->id, $before);
            $this->cancelMove();
        });
    }

    public function drop(int $taskId): void
    {
        $this->onTask($taskId, function (Task $task, CrunchDecisions $decisions) {
            $before = CrunchDecisions::snapshot($task);
            $decisions->drop($this->user, $task->id);
            $this->remember(__('shell.crunch.done.later', ['task' => $task->title]), 'task', $task->id, $before);
        });
    }

    public function startAdd(): void
    {
        $this->cancelMove();
        $this->resetErrorBag();
        $suggestion = $this->view->moreTime;
        $now = UserClock::now($this->user);
        $this->addDate = $suggestion['date'] ?? $now->toDateString();
        $this->addStart = $suggestion['start'] ?? '19:00';
        $this->addEnd = $suggestion['end'] ?? '20:00';
        $this->addCategory = $suggestion['categoryId'] ?? ($this->view->categories[0]->id ?? null);
        $this->adding = true;
    }

    public function cancelAdd(): void
    {
        $this->adding = false;
        $this->resetErrorBag(['addDate', 'addStart', 'addEnd', 'addCategory']);
    }

    public function addTime(): void
    {
        $this->resetErrorBag(['addDate', 'addStart', 'addEnd', 'addCategory']);
        $event = app(CrunchDecisions::class)->addTime($this->user, $this->addDate, $this->addStart, $this->addEnd, $this->addCategory);

        $this->remember(__('shell.crunch.done.time', [
            'day' => DayLabel::for($this->addDate, UserClock::now($this->user)),
            'start' => $this->addStart,
            'end' => $this->addEnd,
        ]), 'time', $event->id);
        $this->adding = false;
    }

    public function undoAt(int $index): void
    {
        $entry = $this->undo[$index] ?? null;
        if ($entry === null) {
            return;
        }
        array_splice($this->undo, $index, 1);
        $decisions = app(CrunchDecisions::class);

        try {
            if ($entry['kind'] === 'time') {
                $decisions->removeTime($this->user, $entry['id']);
            } else {
                $decisions->restore($this->user, $entry['id'], $entry['before'] ?? []);
            }
            $this->notice = __('shell.crunch.undone');
        } catch (ModelNotFoundException) {
            $this->notice = __('shell.crunch.errors.undo_gone');
        }

        $this->changed();
    }

    // ---- helpers -----------------------------------------------------------------------------

    /** Runs a decision on one of the user's open tasks; a task that is gone reloads the list calmly. */
    private function onTask(int $taskId, callable $action): void
    {
        $this->notice = null;
        $task = Task::forUser($this->user)->active()->find($taskId);
        if ($task === null) {
            $this->refreshGone();

            return;
        }

        try {
            $action($task, app(CrunchDecisions::class));
        } catch (ModelNotFoundException) {
            $this->refreshGone();

            return;
        }

        $this->changed();
    }

    private function refreshGone(): void
    {
        $this->notice = __('shell.crunch.errors.gone');
        $this->changed();
    }

    private function remember(string $label, string $kind, int $id, array $before = []): void
    {
        $this->undo[] = ['label' => $label, 'kind' => $kind, 'id' => $id, 'before' => $before];
        $this->undo = array_slice($this->undo, -self::UNDO_LIMIT);
    }

    private function changed(): void
    {
        unset($this->view);
        $this->dispatch('crunch-changed');
    }

    private function dayLabel(string $date): string
    {
        return DayLabel::for($date, UserClock::now($this->user));
    }

    public function render(): View
    {
        return view('livewire.shell.crunch');
    }
}
