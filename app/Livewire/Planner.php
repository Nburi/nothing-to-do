<?php

namespace App\Livewire;

use App\Models\AgendaEntry;
use App\Services\AppModules;
use App\Services\DayPlanner;
use App\Services\PlannerStandardTasks;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Planer" — a day-by-day board. Drag a task onto a day; the day itself,
 * not a specific calendar block, is what's remembered, so moving/resizing a
 * Training block on the Zeitplan never strands what was planned around it.
 * Placement is manual by default; autoFillBacklog() is the one optional,
 * purely-additive convenience (see App\Services\DayPlanner).
 */
#[Layout('layouts.app')]
class Planner extends Component
{
    /** Which "Standardaufgabe" sheet is open (see PlannerStandardTasks::CATALOG); null means closed. */
    public ?string $standardTemplate = null;

    public string $standardDate = '';

    public ?int $standardDuration = null;

    public string $standardStudyMode = 'general';

    public string $standardStudySubject = '';

    public ?int $standardStudyAgendaEntryId = null;

    /** Off by default (users.planner_enabled) — visiting the route directly while it's off just bounces back. */
    public function mount(): void
    {
        if (! auth()->user()->planner_enabled) {
            $this->redirectRoute('app', navigate: true);
        }
    }

    /** HORIZON_DAYS days starting today, each with its capacity and planned tasks. */
    #[Computed]
    public function board(): Collection
    {
        return DayPlanner::board(auth()->user());
    }

    /** Every dated-or-not task/homework with no day yet. */
    #[Computed]
    public function backlog(): Collection
    {
        return DayPlanner::backlog(auth()->user());
    }

    /** Dated backlog items whose deadline has already passed — the always-visible "too late" list. */
    #[Computed]
    public function conflicts(): Collection
    {
        return DayPlanner::conflicts(auth()->user());
    }

    /** Day-plans whose date has passed without the task ever reaching Today — see DayPlanner::rollover(). */
    #[Computed]
    public function rollover(): Collection
    {
        return DayPlanner::rollover(auth()->user());
    }

    /**
     * Persists one day's full order — the destination of a drag, the same
     * "send the whole ordered list" shape TaskBoard::reorder() uses. Each
     * entry is a "task:<id>" or "agenda:<id>" token (see
     * DayPlanner::assignDay() for how an agenda token gets promoted).
     * Ownership of every id is verified inside the service, item by item.
     *
     * @param  array<int, string>  $items
     */
    public function assignDay(string $date, array $items): void
    {
        DayPlanner::assignDay(auth()->user(), $date, $items);
        $this->refreshComputeds();
    }

    /**
     * The mobile day-picker sheet's tap-a-day action (see
     * DayPlanner::moveToDay()) — appends one chip to the end of that day,
     * for the case where the caller doesn't already have the day's full
     * order to hand the way a desktop drag-drop does.
     */
    public function moveToDay(string $token, string $date): void
    {
        DayPlanner::moveToDay(auth()->user(), $token, $date);
        $this->refreshComputeds();
    }

    /** The small "×" on a placed task, and the backlog's own drop zone — releases a task back to the backlog. */
    public function unassignTask(int $taskId): void
    {
        $task = auth()->user()->tasks()->findOrFail($taskId);

        DayPlanner::unassignTask($task);
        $this->refreshComputeds();
    }

    /**
     * "Rest automatisch einplanen" — purely additive, so unlike the old
     * block planner's "Neu planen" this needs no armed confirmation: it can
     * only ever fill tasks that have no day yet, never touch one you've
     * already placed by hand.
     */
    public function autoFillBacklog(): void
    {
        DayPlanner::autoFillBacklog(auth()->user());
        $this->refreshComputeds();
    }

    /** The next HORIZON_DAYS dates as {date, label} — what the "Standardaufgabe" sheet's day picker offers. Same heute/morgen/weekday-d.m. shape the board's own column headers use. */
    #[Computed]
    public function standardTaskDayOptions(): array
    {
        $today = auth()->user()->localToday();
        $wd = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
        $options = [];

        for ($i = 0; $i < DayPlanner::HORIZON_DAYS; $i++) {
            $date = $today->copy()->addDays($i);
            $label = match (true) {
                $i === 0 => 'Heute · '.$wd[$date->dayOfWeek].' '.$date->isoFormat('D.M.'),
                $i === 1 => 'Morgen · '.$wd[$date->dayOfWeek].' '.$date->isoFormat('D.M.'),
                default => $wd[$date->dayOfWeek].' '.$date->isoFormat('D.M.'),
            };
            $options[] = ['date' => $date->toDateString(), 'label' => $label];
        }

        return $options;
    }

    /** Open exam entries to pick from in "Lernen → Für eine Prüfung" — empty while the Agenda module is hidden, mirroring every other Agenda-coupled read in this app. */
    #[Computed]
    public function standardStudyExamOptions(): Collection
    {
        $user = auth()->user();

        if (! AppModules::isVisible($user, 'agenda')) {
            return collect();
        }

        return AgendaEntry::visibleTo($user)->ofType('exam')->openFor($user)->orderBy('date')->get();
    }

    /**
     * Opens the quick-add sheet for one Standardaufgabe, freshly reset — see
     * PlannerStandardTasks::CATALOG. `$date` is optional: the desktop drag
     * source (standardTaskDragSource in app.js) passes the day column it was
     * dropped on, pre-filling the sheet's date field instead of defaulting to
     * today — a plain click (still the mobile/keyboard path) omits it. A
     * given date is re-validated against the real horizon here regardless of
     * where it came from, same "never trust the client" rule as every other
     * write in this app; an invalid/out-of-range value just falls back to
     * today rather than breaking the sheet.
     */
    public function openStandardTask(string $key, ?string $date = null): void
    {
        if (! PlannerStandardTasks::isValidKey($key)) {
            return;
        }

        $user = auth()->user();
        $today = $user->localToday();
        $horizonEnd = $today->copy()->addDays(DayPlanner::HORIZON_DAYS - 1);
        $resolvedDate = $today;

        if ($date !== null) {
            try {
                $parsed = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $date)?->startOfDay();
            } catch (\Throwable) {
                $parsed = null;
            }

            if ($parsed !== null && $parsed->betweenIncluded($today, $horizonEnd)) {
                $resolvedDate = $parsed;
            }
        }

        $this->resetValidation();
        $this->standardTemplate = $key;
        $this->standardDate = $resolvedDate->toDateString();
        $this->standardDuration = $key === 'todos_clear' ? PlannerStandardTasks::DEFAULT_TODOS_DURATION : null;
        $this->standardStudyMode = 'general';
        $this->standardStudySubject = '';
        $this->standardStudyAgendaEntryId = null;
    }

    public function closeStandardTask(): void
    {
        $this->standardTemplate = null;
    }

    /** Switching mode clears the other modes' leftover input, so a stale subject/exam pick can never sneak into a save under a different mode. */
    public function setStandardStudyMode(string $mode): void
    {
        if (! PlannerStandardTasks::isValidStudyMode($mode)) {
            return;
        }

        $this->standardStudyMode = $mode;
        $this->standardStudySubject = '';
        $this->standardStudyAgendaEntryId = null;
    }

    /** Picking an exam pre-fills the free-text subject too, so it still reads correctly (and still saves fine) if the entry turns out to be stale by the time the form is submitted. */
    public function pickStandardStudyExam(int $entryId): void
    {
        $entry = $this->standardStudyExamOptions->firstWhere('id', $entryId);

        if ($entry === null) {
            return;
        }

        $this->standardStudyAgendaEntryId = $entry->id;
        $this->standardStudySubject = $entry->subject;
    }

    /**
     * Builds the actual Task from the filled-in sheet and places it on the
     * chosen day via DayPlanner::moveToDay() — after this, the task is
     * completely ordinary: it shows up in board/backlog/edit sheet exactly
     * like a hand-typed one, nothing about it stays "special".
     */
    public function saveStandardTask(): void
    {
        $user = auth()->user();

        if ($this->standardTemplate === null || ! PlannerStandardTasks::isValidKey($this->standardTemplate) || ! $user->planner_enabled) {
            return;
        }

        $today = $user->localToday();
        $horizonEnd = $today->copy()->addDays(DayPlanner::HORIZON_DAYS - 1);

        $rules = [
            'standardDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today->toDateString(), 'before_or_equal:'.$horizonEnd->toDateString()],
        ];

        if ($this->standardTemplate === 'todos_clear') {
            $rules['standardDuration'] = ['required', 'integer', 'min:'.PlannerStandardTasks::MIN_DURATION, 'max:'.PlannerStandardTasks::MAX_DURATION];
        } else {
            $rules['standardStudyMode'] = ['required', Rule::in(array_keys(PlannerStandardTasks::STUDY_MODES))];

            if ($this->standardStudyMode !== 'general') {
                $rules['standardStudySubject'] = ['required', 'string', 'max:80'];
            }
        }

        $validated = $this->validate($rules);

        if ($this->standardTemplate === 'todos_clear') {
            $title = PlannerStandardTasks::label('todos_clear');
            $duration = $validated['standardDuration'];
            $deadline = null;
        } else {
            $mode = $this->standardStudyMode;
            $subject = $mode === 'general' ? null : $this->standardStudySubject;
            $title = PlannerStandardTasks::studyTitle($mode, $subject);
            $duration = null;

            $examEntry = $mode === 'exam' && $this->standardStudyAgendaEntryId !== null
                ? $this->standardStudyExamOptions->firstWhere('id', $this->standardStudyAgendaEntryId)
                : null;
            $deadline = $examEntry?->date;
        }

        $task = $user->tasks()->create([
            'title' => $title,
            'list' => PlannerStandardTasks::listFor($this->standardTemplate),
            'duration_minutes' => $duration,
            'deadline' => $deadline,
            'sort_order' => 0,
        ]);

        DayPlanner::moveToDay($user, "task:{$task->id}", $validated['standardDate']);

        $this->standardTemplate = null;
        $this->refreshComputeds();
    }

    private function refreshComputeds(): void
    {
        unset($this->board, $this->backlog, $this->conflicts, $this->rollover);
    }

    public function render()
    {
        return view('livewire.planner');
    }
}
