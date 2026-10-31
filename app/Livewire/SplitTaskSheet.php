<?php

namespace App\Livewire;

use App\Models\Task;
use App\Services\DayPlanner;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * "Aufteilen" — spread one task over several days. A bottom sheet mounted once
 * in the layout (like QuickCapture) so it can be opened from the board, a
 * project or group page and the Planer alike: `Livewire.dispatch(
 * 'open-split-sheet', { taskId })`.
 *
 * The sheet is a 14-day strip showing each day's Arbeit-Block capacity and
 * the deadline; the user taps days to put a part on them. The selection is
 * ephemeral client state (Alpine, see `splitSheet` in resources/js/app.js) so
 * a tap costs no round trip; only the app's proposal (suggest), saving and
 * the part tick-offs reach the server. Writes go through DayPlanner, which
 * re-validates every date and checks ownership — nothing here is trusted.
 */
class SplitTaskSheet extends Component
{
    public ?int $taskId = null;

    #[On('open-split-sheet')]
    public function open(int $taskId): void
    {
        $task = auth()->user()->tasks()->find($taskId);

        if ($task === null || ! DayPlanner::canSplit(auth()->user(), $task)) {
            return;
        }

        $this->resetErrorBag();
        $this->taskId = $task->id;
    }

    public function close(): void
    {
        $this->taskId = null;
        $this->resetErrorBag();
    }

    #[Computed]
    public function task(): ?Task
    {
        return $this->taskId === null ? null : auth()->user()->tasks()->with('sessions')->find($this->taskId);
    }

    /** @return array<string, mixed>|null */
    #[Computed]
    public function context(): ?array
    {
        $task = $this->task;

        return $task === null ? null : DayPlanner::splitContext(auth()->user(), $task);
    }

    /**
     * What the Alpine strip starts from: the open parts when the task is
     * already split, otherwise the app's first proposal — computed here so the
     * strip never opens empty and then jumps.
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function sheetConfig(): ?array
    {
        $context = $this->context;
        $task = $this->task;

        if ($context === null || $task === null) {
            return null;
        }

        $selected = $context['selected'];

        if ($selected === []) {
            $selected = DayPlanner::suggestSplitDates(
                auth()->user(),
                $task,
                DayPlanner::defaultPartCount($context['defaultTotal']),
                $context['defaultTotal'],
            );
        }

        return [
            'days' => $context['days'],
            'deadlineOffset' => $context['deadlineOffset'],
            'selected' => $selected,
            'total' => $context['defaultTotal'],
            'doneCount' => $context['doneCount'],
        ];
    }

    /**
     * The app's proposal for $parts days; called by the sheet's "Vorschlag"
     * button and its +/- stepper. Returns plain dates for Alpine to apply.
     *
     * @return array<int, string>
     */
    public function suggest(int $parts, int $total): array
    {
        $task = $this->task;

        if ($task === null) {
            return [];
        }

        return DayPlanner::suggestSplitDates(auth()->user(), $task, $parts, max(DayPlanner::MIN_PART_MINUTES, $total));
    }

    /** @param array<int, string> $dates */
    public function save(array $dates, int $total): void
    {
        $task = $this->task;

        if ($task === null) {
            return;
        }

        $this->resetErrorBag();
        $done = $task->sessions->filter(fn ($s) => $s->isDone())->count();

        if (count($dates) + $done < 2) {
            $this->addError('split', $done > 0 ? 'Wähle mindestens einen weiteren Tag.' : 'Wähle mindestens zwei Tage — sonst ist es keine Aufteilung.');

            return;
        }

        if (! DayPlanner::saveSplit(auth()->user(), $task, $dates, $total)) {
            $this->addError('split', 'Das hat nicht geklappt. Prüfe, ob die Tage in den nächsten '.DayPlanner::HORIZON_DAYS.' Tagen liegen und ob die Aufgabe noch offen ist.');

            return;
        }

        $this->dispatch('plan-changed');
        $this->close();
    }

    /** Ends the split — every part goes, the task returns to "Nicht eingeplant". */
    public function unsplit(): void
    {
        $task = $this->task;

        if ($task !== null) {
            DayPlanner::unsplit(auth()->user(), $task);
            $this->dispatch('plan-changed');
        }

        $this->close();
    }

    /** Ticks a part off or back open from the sheet's part list. */
    public function togglePart(int $sessionId): void
    {
        $session = $this->task?->sessions->firstWhere('id', $sessionId);

        if ($session === null) {
            return;
        }

        $result = DayPlanner::completeSession(auth()->user(), $sessionId, ! $session->isDone());

        if ($result !== null) {
            $this->dispatch('plan-changed');

            if ($result['celebration'] !== null) {
                $this->dispatch('celebrate', kind: $result['celebration']['kind'], label: $result['celebration']['label']);
            }
        }

        unset($this->task, $this->context);
    }

    public function render()
    {
        return view('livewire.split-task-sheet');
    }
}
