<?php

namespace App\Livewire\Shell;

use App\Models\Area;
use App\Models\Task;
use App\Services\Planning\InboxSort as InboxSortService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The daily sort card (R3, step 2.3): one Inbox item at a time over the board, with a progress
 * bar. 1 / 2 / 3 pick the list, Enter places it, Esc closes; "Später sortieren" moves it to the
 * back of this round. Every placement can be taken back from the toast (CONVENTIONS 7).
 * Same card in Werkbank and Stapel. The writing lives in App\Services\Planning\InboxSort.
 */
class InboxSort extends Component
{
    public bool $open = false;

    /** @var list<int> Inbox ids of this round, in order; skipped ones go to the back. */
    public array $queue = [];

    /** How many were in the Inbox when the round started (progress bar). */
    public int $total = 0;

    public int $placed = 0;

    public string $list = 'tasks';

    public ?string $size = null;

    public ?string $hardDeadline = null;

    public ?string $softDeadline = null;

    public ?int $areaId = null;

    public ?string $firstStep = null;

    public bool $more = false;

    /** The last placement, for undo: [task id, fields before]. */
    public ?array $last = null;

    public ?string $toast = null;

    public ?string $notice = null;

    #[On('open-sort')]
    public function start(): void
    {
        $this->queue = Task::forUser(Auth::user())->active()->where('list', 'inbox')
            ->orderBy('sort_order')->orderBy('id')->pluck('id')->all();
        $this->total = count($this->queue);
        $this->placed = 0;
        $this->last = null;
        $this->toast = null;
        $this->notice = null;
        $this->resetForm();
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
        $this->toast = null;
    }

    #[Computed]
    public function current(): ?Task
    {
        foreach ($this->queue as $id) {
            $task = Task::forUser(Auth::user())->active()->where('list', 'inbox')->find($id);
            if ($task) {
                return $task;
            }
        }

        return null;
    }

    /** @return Collection<int, Area> */
    #[Computed]
    public function areas(): Collection
    {
        return Area::forUser(Auth::user())->ordered()->get();
    }

    /** How many open items each target list holds (the piles of the phone deck). */
    #[Computed]
    public function counts(): array
    {
        return Task::forUser(Auth::user())->active()
            ->whereIn('list', InboxSortService::TARGETS)
            ->selectRaw('list, count(*) as n')
            ->groupBy('list')
            ->pluck('n', 'list')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /** The phone deck: the card was thrown onto (or the pile tapped for) one list. */
    public function placeTo(string $list, InboxSortService $sort): void
    {
        if (! in_array($list, InboxSortService::TARGETS, true)) {
            return;
        }

        $this->list = $list;
        $this->place($sort);
        unset($this->counts);
    }

    public function pick(string $list): void
    {
        if (in_array($list, InboxSortService::TARGETS, true)) {
            $this->list = $list;
        }
    }

    public function place(InboxSortService $sort): void
    {
        $task = $this->current;
        if ($task === null) {
            return;
        }

        try {
            $result = $sort->sort(Auth::user(), $task->id, [
                'list' => $this->list,
                'size' => $this->size,
                'hard_deadline' => $this->blankToNull($this->hardDeadline),
                'soft_deadline' => $this->blankToNull($this->softDeadline),
                'area_id' => $this->areaId,
                'first_step' => $this->blankToNull($this->firstStep),
            ]);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());
            $this->more = $this->more || $e->validator->errors()->hasAny(['hard_deadline', 'soft_deadline', 'area_id', 'first_step']);

            return;
        } catch (ModelNotFoundException) {
            $this->dropFromQueue($task->id);
            $this->notice = __('sort.gone');

            return;
        }

        $placed = $result['task'];
        $this->last = ['id' => $placed->id, 'before' => $result['before']];
        $this->toast = $placed->list === Task::LATER
            ? __('sort.placed_later')
            : __('sort.placed', ['list' => __('shell.lists.'.$placed->list), 'size' => __('shell.sizes.'.$placed->effectiveSize()->value)]);
        $this->notice = null;
        $this->placed++;
        $this->dropFromQueue($placed->id);
        $this->resetForm();
        unset($this->counts);
        $this->dispatch('inbox-changed');
    }

    public function undo(InboxSortService $sort): void
    {
        if ($this->last === null) {
            return;
        }

        try {
            $sort->undo(Auth::user(), $this->last['id'], $this->last['before']);
            array_unshift($this->queue, $this->last['id']);
            $this->placed = max(0, $this->placed - 1);
            $this->notice = __('sort.undone');
        } catch (ModelNotFoundException) {
            $this->notice = __('shell.errors.task_gone');
        }

        $this->last = null;
        $this->toast = null;
        $this->open = true;
        unset($this->current, $this->counts);
        $this->dispatch('inbox-changed');
    }

    /** "Später sortieren": to the back of this round, nothing written. */
    public function skip(): void
    {
        $task = $this->current;
        if ($task === null) {
            return;
        }

        $this->dropFromQueue($task->id);
        $this->queue[] = $task->id;
        $this->resetForm();
    }

    private function dropFromQueue(int $id): void
    {
        $this->queue = array_values(array_filter($this->queue, fn (int $q) => $q !== $id));
        unset($this->current);
    }

    private function resetForm(): void
    {
        $this->list = 'tasks';
        $this->size = null;
        $this->hardDeadline = null;
        $this->softDeadline = null;
        $this->areaId = null;
        $this->firstStep = null;
        $this->more = false;
        $this->resetErrorBag();
    }

    private function blankToNull(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }

    public function render(): View
    {
        return view('livewire.shell.inbox-sort');
    }
}
