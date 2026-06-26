<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesTasks;
use App\Models\Project;
use App\Models\ScheduleEvent;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TaskBoard extends Component
{
    use ManagesTasks;

    /** Quick-add. */
    public string $newTitle = '';

    public string $newList = 'inbox';

    public ?string $newDeadline = null;

    public ?string $newDueDate = null;

    /** Add-project (Projects column / tab). */
    public string $newProjectName = '';

    public ?string $newProjectDeadline = null;

    /** Active mobile page: inbox | todos | tasks | today | projects. */
    public string $mobileTab = 'inbox';

    // ── Reads (computed, cached per request) ──────────────────────────

    /**
     * Tasks visible in a board column: active tasks + tasks completed within the
     * current visibility window (since the user's daily reset time). Active tasks
     * come first (orderBy is_completed ASC prepended before boardOrdered).
     *
     * @return Collection<int, Task>
     */
    private function boardTasks(string $list): Collection
    {
        $windowStart = auth()->user()->completedWindowStart();

        return Task::query()
            ->forUser(auth()->user())
            ->onBoard()
            ->inList($list)
            ->where(function ($q) use ($windowStart) {
                $q->where('is_completed', false)
                    ->orWhere(function ($q2) use ($windowStart) {
                        $q2->where('is_completed', true)
                            ->where('completed_at', '>=', $windowStart);
                    });
            })
            ->orderBy('is_completed')   // active (0) before completed (1)
            ->boardOrdered()
            ->get();
    }

    #[Computed]
    public function inbox(): Collection
    {
        return $this->boardTasks('inbox');
    }

    /** Whole-list collections (active + recently completed), fetched once and split below. */
    #[Computed]
    public function todosAll(): Collection
    {
        return $this->boardTasks('todos');
    }

    #[Computed]
    public function tasksAll(): Collection
    {
        return $this->boardTasks('tasks');
    }

    /** Only active tasks in today's focus — flagged today, or planned for today by the Brief. */
    #[Computed]
    public function todosToday(): Collection
    {
        return $this->todosAll->where('is_completed', false)->filter->isTodayFocus()->values();
    }

    /** Active todos not in today's focus (completed ones are passed separately to the column partial). */
    #[Computed]
    public function todosRest(): Collection
    {
        return $this->todosAll->where('is_completed', false)->reject->isTodayFocus()->values();
    }

    #[Computed]
    public function tasksToday(): Collection
    {
        return $this->tasksAll->where('is_completed', false)->filter->isTodayFocus()->values();
    }

    #[Computed]
    public function tasksRest(): Collection
    {
        return $this->tasksAll->where('is_completed', false)->reject->isTodayFocus()->values();
    }

    /** Mobile "Today" page: every focused board task across todos + tasks. */
    #[Computed]
    public function today(): Collection
    {
        return Task::query()
            ->forUser(auth()->user())
            ->active()
            ->onBoard()
            ->where(fn ($q) => $q->where('is_today', true)
                ->orWhereDate('planned_for', Carbon::today()->toDateString()))
            ->boardOrdered()
            ->get();
    }

    /**
     * Standalone tasks placed in the Projects column but not inside any project.
     * These are on-board tasks (project_id IS NULL) with list = 'projects'.
     *
     * @return Collection<int, Task>
     */
    #[Computed]
    public function projectTasks(): Collection
    {
        return $this->boardTasks('projects');
    }

    /**
     * Projects with their working set: every active task (ordered) for the
     * card preview + open count, plus a completed count for the progress label.
     *
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projects(): Collection
    {
        return Project::query()
            ->forUser(auth()->user())
            ->ordered()
            ->withCount(['tasks as done_count' => fn ($q) => $q->where('is_completed', true)])
            ->with('activeTasks')
            ->get();
    }

    /** Today's timeline events (recurring series materialised on read). */
    #[Computed]
    public function scheduleToday(): Collection
    {
        $today = Carbon::today();

        ScheduleEvent::materializeRange(auth()->user(), $today, $today->copy());

        return ScheduleEvent::forUser(auth()->user())
            ->visible()
            ->forDay($today)
            ->ordered()
            ->get();
    }

    /**
     * The Work-/To-Do-Session that is running now, or starts within 5 minutes —
     * the trigger that swaps the header strip for the focus timer.
     */
    #[Computed]
    public function focusSession(): ?ScheduleEvent
    {
        $now = now();
        $nowMin = $now->hour * 60 + $now->minute;

        return $this->scheduleToday->first(function (ScheduleEvent $e) use ($now, $nowMin) {
            if (! $e->isWorkSession()) {
                return false;
            }

            $untilStart = $e->startMinutes() - $nowMin;

            return $e->isActive($now) || ($untilStart >= 0 && $untilStart <= 5);
        });
    }

    /**
     * Whether to nudge the Brief: past the configured time and not already
     * dismissed today. The app only suggests — the user starts it manually.
     */
    #[Computed]
    public function showBriefNudge(): bool
    {
        $user = auth()->user();

        if ($user->brief_dismissed_on?->isToday()) {
            return false;
        }

        return now()->format('H:i') >= ($user->brief_time ?? '19:00');
    }

    /** Greeting + how much is waiting, for the nudge banner. */
    #[Computed]
    public function briefNudge(): array
    {
        $user = auth()->user();
        $target = $user->briefTargetDate();
        $wd = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
        $hour = now()->hour;

        $dueSoon = Task::query()->forUser($user)->active()->onBoard()
            ->whereIn('list', ['todos', 'tasks'])
            ->where(fn ($q) => $q->whereDate('deadline', '<=', $target->toDateString())
                ->orWhereDate('due_date', '<=', $target->toDateString()))
            ->count();

        return [
            'greeting' => $hour < 11 ? 'Guten Morgen' : ($hour < 18 ? 'Guten Tag' : 'Guten Abend'),
            'dayName' => $wd[$target->dayOfWeekIso - 1],
            'waiting' => $this->counts['todos'] + $this->counts['tasks'],
            'dueSoon' => $dueSoon,
        ];
    }

    public function dismissBrief(): void
    {
        auth()->user()->update(['brief_dismissed_on' => Carbon::today()->toDateString()]);
    }

    /** Active-task counts only — completed tasks don't inflate the badges. */
    #[Computed]
    public function counts(): array
    {
        return [
            'inbox'    => $this->inbox->where('is_completed', false)->count(),
            'todos'    => $this->todosAll->where('is_completed', false)->count(),
            'tasks'    => $this->tasksAll->where('is_completed', false)->count(),
            'today'    => $this->today->count(),
            'projects' => $this->projects->count() + $this->projectTasks->where('is_completed', false)->count(),
        ];
    }

    // ── Writes (all ownership-scoped) ─────────────────────────────────

    public function setMobileTab(string $tab): void
    {
        if (! in_array($tab, ['inbox', 'todos', 'tasks', 'today', 'projects'], true)) {
            return;
        }

        $this->mobileTab = $tab;

        // Quick-add on a board-list tab targets that list. Today & Projects
        // don't feed the task quick-add, so leave the target untouched.
        if (in_array($tab, Task::BOARD_LISTS, true)) {
            $this->newList = $tab;
        }
    }

    public function addTask(): void
    {
        // Trim first so a whitespace-only title fails the required rule.
        $this->newTitle = trim($this->newTitle);

        $data = $this->validate([
            'newTitle'    => ['required', 'string', 'max:255'],
            'newList'     => ['required', 'in:inbox,todos,tasks'],
            'newDeadline' => ['nullable', 'date'],
            'newDueDate'  => ['nullable', 'date'],
        ]);

        auth()->user()->tasks()->create([
            'title'    => $data['newTitle'],
            'list'     => $data['newList'],
            'deadline' => $data['newDeadline'] ?: null,
            'due_date' => $data['newDueDate'] ?: null,
            'sort_order' => 0,
        ]);

        $this->reset(['newTitle', 'newDeadline', 'newDueDate']);
        $this->dispatch('task-added');
    }

    public function addProject(): void
    {
        $this->newProjectName = trim($this->newProjectName);

        $data = $this->validate([
            'newProjectName'     => ['required', 'string', 'max:255'],
            'newProjectDeadline' => ['nullable', 'date'],
        ]);

        auth()->user()->projects()->create([
            'name'     => $data['newProjectName'],
            'deadline' => $data['newProjectDeadline'] ?: null,
            'sort_order' => 0,
        ]);

        $this->reset(['newProjectName', 'newProjectDeadline']);
        $this->dispatch('project-added');
    }

    /**
     * Desktop drag & drop: a board task card dropped onto a project card.
     * Moves the task into that project (and off the board / out of Today).
     */
    public function assignTaskToProject(int $taskId, int $projectId): void
    {
        $task = $this->userTask($taskId);
        $project = auth()->user()->projects()->findOrFail($projectId);

        $task->update([
            'project_id' => $project->id,
            'list' => 'projects',
            'is_today' => false,
        ]);
    }

    /** Set/clear the Today focus. Inbox & project tasks can never be Today. */
    public function setToday(int $id, bool $value): void
    {
        $task = $this->userTask($id);

        if ($task->isInbox() || $task->isInProject()) {
            return;
        }

        // Leaving Today also clears a Brief plan, so it doesn't linger back in.
        $task->update([
            'is_today' => $value,
            'planned_for' => $value ? $task->planned_for : null,
        ]);
    }

    /**
     * Drag & drop persistence. Receives the full ordered list of task ids now
     * sitting in one zone (a column, or a column's Today area) and rewrites
     * their list / today / order to match. The source zone needs no update —
     * a moved task simply drops out of its old column's query.
     *
     * @param  array<int, int|string>  $ids
     */
    public function reorder(string $list, bool $today, array $ids): void
    {
        // Board columns + standalone project list are valid drag targets.
        if (! in_array($list, [... Task::BOARD_LISTS, 'projects'], true)) {
            return;
        }

        // Inbox and project list have no Today area.
        $today = in_array($list, ['inbox', 'projects'], true) ? false : $today;

        foreach (array_values($ids) as $position => $id) {
            $task = auth()->user()->tasks()->find((int) $id);

            if ($task === null) {
                continue; // ignore ids that aren't ours
            }

            $updates = [
                'list'       => $list,
                'is_today'   => $today,
                'sort_order' => $position,
            ];

            // Moving into the standalone project list clears the project assignment.
            if ($list === 'projects') {
                $updates['project_id'] = null;
            }

            $task->update($updates);
        }
    }

    /** Mobile swipe outcomes. */
    public function swipeIntent(int $id, string $intent): void
    {
        $task = $this->userTask($id);

        match ($intent) {
            'todos' => $task->update(['list' => 'todos']),
            'tasks' => $task->update(['list' => 'tasks']),
            'today' => $task->isInbox() ? null : $task->update(['is_today' => true]),
            'untoday' => $task->isInbox() ? null : $task->update(['is_today' => false, 'planned_for' => null]),
            default => null,
        };
    }

    public function render()
    {
        return view('livewire.task-board');
    }
}
