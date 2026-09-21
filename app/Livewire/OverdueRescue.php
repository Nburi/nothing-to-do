<?php

namespace App\Livewire;

use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * A calm way out of "everything I planned for last week is red": a banner on
 * the board that offers to move every soft-overdue task in one tap — to today,
 * tomorrow or next Monday, or to drop the date entirely — and to undo that.
 *
 * Only *soft* dates (`due_date`, the self-imposed Wunschtermin) are offered.
 * A hard `deadline` that has passed is somebody else's date; silently moving
 * it would be the app lying about the world, so those are only counted, never
 * touched. Every action re-queries at click time instead of trusting what was
 * last rendered — the banner may be a few seconds stale (a task completed on
 * the board doesn't re-render it), and a stale list must never move a task
 * that has since been finished or edited.
 */
class OverdueRescue extends Component
{
    /** When a move is applied, what it did — the undo strip's text. */
    public ?string $appliedLabel = null;

    /**
     * task id => the due_date it had before the last move (Y-m-d). Locked:
     * the client can read it but never write it, so "undo" can only ever
     * restore what this component itself recorded.
     *
     * @var array<int, string>
     */
    #[Locked]
    public array $previousDates = [];

    /** New captures and every task change elsewhere on the board also re-render us. */
    #[On('captured')]
    public function refresh(): void
    {
        // handling the event is the re-render; every read is a computed property
    }

    /**
     * Soft-overdue, still-open board tasks: no hard deadline, a due date before
     * today (in the user's own local day).
     *
     * @return Collection<int, Task>
     */
    #[Computed]
    public function softOverdue(): Collection
    {
        $user = auth()->user();

        return Task::query()
            ->forUser($user)
            ->active()
            ->onBoard()
            ->whereNull('deadline')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $user->localToday()->toDateString())
            ->orderBy('due_date')
            ->get(['id', 'title', 'due_date']);
    }

    /** Hard deadlines already missed — counted for the banner's second line, never moved. */
    #[Computed]
    public function hardOverdueCount(): int
    {
        $user = auth()->user();

        return Task::query()
            ->forUser($user)
            ->active()
            ->onBoard()
            ->whereNotNull('deadline')
            ->whereDate('deadline', '<', $user->localToday()->toDateString())
            ->count();
    }

    #[Computed]
    public function dismissedToday(): bool
    {
        return session('overdue_rescue_dismissed_on') === auth()->user()->localToday()->toDateString();
    }

    /** @param  'today'|'tomorrow'|'monday'|'clear'  $when */
    public function reschedule(string $when): void
    {
        $today = auth()->user()->localToday();

        $target = match ($when) {
            'today' => $today->copy(),
            'tomorrow' => $today->copy()->addDay(),
            'monday' => $today->copy()->next(Carbon::MONDAY),
            'clear' => null,
            default => false,
        };

        if ($target === false) {
            return;
        }

        $tasks = $this->softOverdue;

        if ($tasks->isEmpty()) {
            return;
        }

        $this->previousDates = $tasks->mapWithKeys(fn (Task $t) => [$t->id => $t->due_date->toDateString()])->all();

        Task::query()->forUser(auth()->user())->whereKey($tasks->pluck('id'))
            ->update(['due_date' => $target?->toDateString()]);

        $count = $tasks->count();
        $noun = $count === 1 ? 'Aufgabe' : 'Aufgaben';
        $this->appliedLabel = match ($when) {
            'today' => "{$count} {$noun} auf heute gelegt.",
            'tomorrow' => "{$count} {$noun} auf morgen gelegt.",
            'monday' => "{$count} {$noun} auf Montag gelegt.",
            default => "Bei {$count} {$noun} ist das Datum entfernt.",
        };

        unset($this->softOverdue);
        $this->dispatch('captured')->to(TaskBoard::class);
    }

    /** Restore exactly what the last move changed — and only where it is still safe to. */
    public function undo(): void
    {
        foreach ($this->previousDates as $id => $date) {
            Task::query()
                ->forUser(auth()->user())
                ->whereKey($id)
                ->where('is_completed', false)
                ->update(['due_date' => $date]);
        }

        $this->previousDates = [];
        $this->appliedLabel = null;
        unset($this->softOverdue);
        $this->dispatch('captured')->to(TaskBoard::class);
    }

    /** Close the "moved" confirmation; the move itself stands. */
    public function acknowledge(): void
    {
        $this->previousDates = [];
        $this->appliedLabel = null;
    }

    /** "Nicht jetzt": hide the banner for the rest of the local day. */
    public function dismiss(): void
    {
        session(['overdue_rescue_dismissed_on' => auth()->user()->localToday()->toDateString()]);
        unset($this->dismissedToday);
    }

    public function render()
    {
        return view('livewire.overdue-rescue');
    }
}
