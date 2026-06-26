<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesSchedule;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Services\ScheduleGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The evening (or morning) planning ritual. Three steps:
 *   1. Update the day's appointments + paint the free time to work in.
 *   2. Pick To-Dos and Tasks (with a session count) — live capacity meter.
 *   3. Review the generated Pomodoro plan, then commit it.
 *
 * Committing marks the chosen items for the planned day (planned_for) and
 * writes the generated sessions/breaks onto that day's timeline.
 */
#[Layout('layouts.app')]
class Brief extends Component
{
    use ManagesSchedule;

    public const DAY_START = 6 * 60;

    public const DAY_END = 23 * 60;

    public const MAX_SESSIONS_PER_TASK = 8;

    public int $step = 1;

    /** The day being planned (tomorrow in the evening, today in the morning). */
    public string $targetDate = '';

    /** Painted free-time ranges, minutes from midnight: [['start'=>,'end'=>], …]. */
    public array $freeBlocks = [];

    /** Selected To-Do ids (they share the single To-Do-Session). */
    public array $selectedTodos = [];

    /** Selected Task ids → session count. */
    public array $taskSessions = [];

    public function mount(): void
    {
        $this->targetDate = auth()->user()->briefTargetDate()->toDateString();

        ScheduleEvent::materializeRange(
            auth()->user(),
            Carbon::parse($this->targetDate),
            Carbon::parse($this->targetDate),
        );

        $this->prefillSuggestions();
    }

    /** Pre-select the items the app would suggest: important or time-pressured. */
    private function prefillSuggestions(): void
    {
        foreach ($this->candidateTasks as $task) {
            if ($task->is_important || $task->isUrgent()) {
                $this->taskSessions[$task->id] = $task->estimated_sessions ?? 1;
            }
        }

        $this->selectedTodos = $this->candidateTodos
            ->filter(fn (Task $t) => $t->is_important || $t->isUrgent())
            ->pluck('id')
            ->all();
    }

    // ── Reads ─────────────────────────────────────────────────────────

    /** Appointments already on the planned day. */
    #[Computed]
    public function dayAppointments(): Collection
    {
        return ScheduleEvent::forUser(auth()->user())
            ->visible()
            ->forDay($this->targetDate)
            ->where('type', ScheduleEvent::TYPE_APPOINTMENT)
            ->ordered()
            ->get();
    }

    /** Active board Tasks, most pressing first (deadline, then how long they've waited). */
    #[Computed]
    public function candidateTasks(): Collection
    {
        return $this->prioritise(
            Task::query()->forUser(auth()->user())->active()->onBoard()->inList('tasks')->get()
        );
    }

    #[Computed]
    public function candidateTodos(): Collection
    {
        return $this->prioritise(
            Task::query()->forUser(auth()->user())->active()->onBoard()->inList('todos')->get()
        );
    }

    private function prioritise(Collection $tasks): Collection
    {
        return $tasks->sortBy([
            fn (Task $t) => $t->effectiveDate()?->toDateString() ?? '9999-12-31',
            fn (Task $t) => $t->created_at->timestamp,
        ])->values();
    }

    private function generator(): ScheduleGenerator
    {
        return ScheduleGenerator::forUser(auth()->user());
    }

    /** How many sessions fit in the painted free time. */
    #[Computed]
    public function capacity(): int
    {
        return $this->generator()->capacity($this->freeBlocks);
    }

    /** How many sessions the current selection demands. */
    #[Computed]
    public function demand(): int
    {
        return array_sum($this->taskSessions) + (count($this->selectedTodos) > 0 ? 1 : 0);
    }

    /** Total painted free minutes (for the Step 1 summary). */
    #[Computed]
    public function freeMinutes(): int
    {
        return collect($this->freeBlocks)->sum(fn ($b) => $b['end'] - $b['start']);
    }

    /** How many sessions fit in a single block — used for the per-block label. */
    public function sessionsIn(int $start, int $end): int
    {
        return $this->generator()->capacity([['start' => $start, 'end' => $end]]);
    }

    /** The generated plan for the review step. */
    #[Computed]
    public function plan(): array
    {
        return $this->generator()->generate($this->freeBlocks, $this->buildUnits());
    }

    /** Whether a Brief plan already exists for the target day (so committing replaces it). */
    #[Computed]
    public function hasExistingPlan(): bool
    {
        return auth()->user()->scheduleEvents()
            ->where('date', $this->targetDate)
            ->where('source', 'brief')
            ->exists();
    }

    /**
     * The ordered focus queue: one To-Do-Session (if any todos), then one
     * Work-Session per Task-session in priority order.
     *
     * @return array<int, array{kind:string, task_id?:int}>
     */
    private function buildUnits(): array
    {
        $units = [];

        if (count($this->selectedTodos) > 0) {
            $units[] = ['kind' => 'todo'];
        }

        foreach ($this->candidateTasks as $task) {
            $sessions = $this->taskSessions[$task->id] ?? 0;
            for ($i = 0; $i < $sessions; $i++) {
                $units[] = ['kind' => 'work', 'task_id' => $task->id];
            }
        }

        return $units;
    }

    // ── Step navigation ───────────────────────────────────────────────

    public function nextStep(): void
    {
        // Don't leave step 1 without any free time painted — there'd be nothing to
        // plan, and finalising would wipe an existing plan for the day.
        if ($this->step === 1 && $this->freeMinutes === 0) {
            return;
        }

        $this->step = min(3, $this->step + 1);
    }

    public function prevStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    // ── Step 1: free-time painting ────────────────────────────────────

    public function addFreeBlock(int $start, int $end): void
    {
        $start = max(self::DAY_START, min(self::DAY_END, $start));
        $end = max(self::DAY_START, min(self::DAY_END, $end));

        if ($end - $start < auth()->user()->pomodoro()['work']) {
            return; // too short for even one session
        }

        $this->freeBlocks[] = ['start' => $start, 'end' => $end];
        $this->mergeFreeBlocks();
        unset($this->capacity, $this->freeMinutes, $this->plan);
    }

    public function removeFreeBlock(int $index): void
    {
        unset($this->freeBlocks[$index]);
        $this->freeBlocks = array_values($this->freeBlocks);
        unset($this->capacity, $this->freeMinutes, $this->plan);
    }

    /** Keep blocks sorted and merge any that overlap or touch. */
    private function mergeFreeBlocks(): void
    {
        usort($this->freeBlocks, fn ($a, $b) => $a['start'] <=> $b['start']);

        $merged = [];
        foreach ($this->freeBlocks as $b) {
            if ($merged !== [] && $b['start'] <= end($merged)['end']) {
                $merged[count($merged) - 1]['end'] = max(end($merged)['end'], $b['end']);
            } else {
                $merged[] = $b;
            }
        }

        $this->freeBlocks = $merged;
    }

    // ── Step 2: selection ─────────────────────────────────────────────

    public function toggleTodo(int $id): void
    {
        if (in_array($id, $this->selectedTodos, true)) {
            $this->selectedTodos = array_values(array_diff($this->selectedTodos, [$id]));
        } elseif (auth()->user()->tasks()->whereKey($id)->exists()) {
            $this->selectedTodos[] = $id;
        }

        unset($this->demand, $this->plan);
    }

    public function toggleTask(int $id): void
    {
        if (isset($this->taskSessions[$id])) {
            unset($this->taskSessions[$id]);
            unset($this->demand, $this->plan);

            return;
        }

        $task = auth()->user()->tasks()->find($id);

        if ($task === null) {
            return; // not ours — ignore
        }

        $this->taskSessions[$id] = $task->estimated_sessions ?? 1;
        unset($this->demand, $this->plan);
    }

    public function setSessions(int $id, int $sessions): void
    {
        if (! isset($this->taskSessions[$id])) {
            return;
        }

        $this->taskSessions[$id] = max(1, min(self::MAX_SESSIONS_PER_TASK, $sessions));
        unset($this->demand, $this->plan);
    }

    // ── Step 3: commit ────────────────────────────────────────────────

    public function finalize()
    {
        $user = auth()->user();
        $plan = $this->generator()->generate($this->freeBlocks, $this->buildUnits());

        // Re-running the Brief replaces the previous plan for this day.
        $user->scheduleEvents()->where('date', $this->targetDate)->where('source', 'brief')->delete();
        $user->tasks()->whereDate('planned_for', $this->targetDate)->update(['planned_for' => null]);

        $now = now();
        $rows = [];
        foreach ($plan['events'] as $e) {
            $rows[] = [
                'user_id' => $user->id,
                'suggested_task_id' => $e['suggested_task_id'] ?? null,
                'type' => $e['type'],
                'title' => $e['title'] ?? null,
                'color' => $e['color'] ?? 'forest',
                'date' => $this->targetDate,
                'start_time' => $e['start_time'],
                'end_time' => $e['end_time'],
                'source' => 'brief',
                'is_cancelled' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($rows !== []) {
            ScheduleEvent::insert($rows);
        }

        // Mark the chosen items for the planned day.
        foreach ($this->taskSessions as $id => $sessions) {
            $user->tasks()->whereKey($id)->update([
                'planned_for' => $this->targetDate,
                'estimated_sessions' => $sessions,
            ]);
        }
        if ($this->selectedTodos !== []) {
            $user->tasks()->whereKey($this->selectedTodos)->update(['planned_for' => $this->targetDate]);
        }

        $user->update(['brief_dismissed_on' => Carbon::today()->toDateString()]);

        session()->flash('brief-done', true);

        return $this->redirect(route('schedule'), navigate: true);
    }

    public function render()
    {
        return view('livewire.brief', [
            'dayStart' => self::DAY_START,
            'dayEnd' => self::DAY_END,
        ]);
    }
}
