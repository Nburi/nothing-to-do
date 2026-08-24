<?php

namespace App\Services;

use App\Models\AgendaEntry;
use App\Models\EventCategory;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

/**
 * Picks "what to work on" for a Pomodoro work session. Tiered:
 *
 *   1. Notfallmodus       → the active emergency project's next step, if any.
 *   2. Event task link    → the running session's *own* schedule entry has a
 *                            task bound directly to it (ScheduleEvent::
 *                            linked_task_id) — more specific than its
 *                            category's link, so it wins for this occurrence.
 *   3. Category link      → the running session's category's linked
 *                            project/group/pinned tasks/Agenda entry/generic
 *                            homework nudge/free text, if the category has one
 *                            (see EventCategory::$task_source). Applies on
 *                            every cycle of that session, not just the first.
 *   4. Cycle 1            → a generic nudge to clear the ToDos list.
 *   5. Any cycle          → the top active "today" task (board order).
 *   6. Fallback           → a project's next task or another active
 *                            todos/tasks-list task, picked deterministically
 *                            (stable across the header ring's 5s poll) from
 *                            a seed tied to the session + cycle.
 *
 * Falls through tiers whenever one has nothing to offer (an empty/deleted/
 * already-completed link included), so a spent link never produces a dead
 * suggestion — it just quietly hands off to the next tier.
 */
class TaskSuggestor
{
    /**
     * Returns null, or one of:
     *   ['kind' => 'todos'|'agenda_generic'|'category_text', 'title' => string, 'subtitle' => ?string]
     *   ['kind' => 'task', 'title' => string, 'task_id' => int]
     *   ['kind' => 'project', 'title' => string, 'subtitle' => string, 'project_id' => int]
     *   ['kind' => 'category_group', 'title' => string, 'subtitle' => string, 'group_id' => int]
     *   ['kind' => 'category_agenda', 'title' => string, 'subtitle' => string, 'agenda_entry_id' => int]
     */
    public static function suggest(User $user, int $cycle, int $seedKey, ?EventCategory $category = null, ?Task $linkedTask = null): ?array
    {
        if ($user->emergency_project_id !== null) {
            $emergency = self::emergencySuggestion($user);

            if ($emergency !== null) {
                return $emergency;
            }

            // Nothing left in the emergency project — fall through to normal tiers.
        }

        if ($linkedTask !== null && ! $linkedTask->is_completed) {
            return [
                'kind' => 'task',
                'title' => $linkedTask->title,
                'task_id' => $linkedTask->id,
            ];
        }

        if ($category !== null && $category->task_source !== null) {
            $linked = self::categorySuggestion($category, $user);

            if ($linked !== null) {
                return $linked;
            }

            // Linked source is empty, deleted, or (for agenda_entry) already done — fall through.
        }

        if ($cycle === 1) {
            $openTodos = Task::forUser($user)->active()->inList('todos')->count();

            if ($openTodos > 0) {
                return [
                    'kind' => 'todos',
                    'title' => 'ToDos erledigen',
                    'subtitle' => $openTodos === 1 ? '1 offen' : "{$openTodos} offen",
                ];
            }
        }

        $todayTask = Task::forUser($user)->active()->onBoard()
            ->where('is_today', true)
            ->boardOrdered()
            ->first();

        if ($todayTask !== null) {
            return [
                'kind' => 'task',
                'title' => $todayTask->title,
                'task_id' => $todayTask->id,
            ];
        }

        return self::randomFallback($user, $seedKey, $cycle);
    }

    /**
     * How many active/open items are left in a category's linked source, or
     * null when there is nothing to count (no link, a free-text link, or the
     * linked project/group/entry was deleted). Used both above (indirectly,
     * via categorySuggestion) and by TaskBoard::linkedSourceNotice() to detect
     * the exact moment a linked list is cleared during a running session.
     */
    public static function linkedSourceRemainingCount(EventCategory $category, User $user): ?int
    {
        return match ($category->task_source) {
            'project' => $category->linkedProject?->activeTasks()->count(),
            'group' => $category->linkedGroup?->activeTasks()->count(),
            'tasks' => $category->pinnedTasks()->active()->count(),
            'agenda_entry' => $category->linkedAgendaEntry === null
                ? null
                : ($category->linkedAgendaEntry->isDoneFor($user) ? 0 : 1),
            'agenda_generic' => AgendaEntry::visibleTo($user)->ofType('homework')->openFor($user)->count(),
            default => null, // 'text', or no source set
        };
    }

    /** The next un-done step in the active emergency project's arranged sequence, if any is left. */
    private static function emergencySuggestion(User $user): ?array
    {
        $project = Project::forUser($user)->find($user->emergency_project_id);

        if ($project === null) {
            return null;
        }

        $next = $project->tasks()->active()->orderBy('sort_order')->orderBy('created_at')->first();

        if ($next === null) {
            return null;
        }

        return [
            'kind' => 'emergency',
            'title' => $next->title,
            'subtitle' => $project->name,
            'task_id' => $next->id,
        ];
    }

    /** Dispatches to the one branch matching the category's current task_source. */
    private static function categorySuggestion(EventCategory $category, User $user): ?array
    {
        return match ($category->task_source) {
            'project' => self::categoryProjectSuggestion($category),
            'group' => self::categoryGroupSuggestion($category),
            'tasks' => self::categoryPinnedTaskSuggestion($category),
            'agenda_entry' => self::categoryAgendaEntrySuggestion($category, $user),
            'agenda_generic' => self::categoryAgendaGenericSuggestion($user),
            'text' => self::categoryTextSuggestion($category),
            default => null,
        };
    }

    private static function categoryProjectSuggestion(EventCategory $category): ?array
    {
        $project = $category->linkedProject;

        if ($project === null) {
            return null;
        }

        $next = $project->activeTasks->first();

        if ($next === null) {
            return null;
        }

        return [
            'kind' => 'project',
            'title' => $project->name,
            'subtitle' => $next->title,
            'project_id' => $project->id,
        ];
    }

    private static function categoryGroupSuggestion(EventCategory $category): ?array
    {
        $group = $category->linkedGroup;

        if ($group === null) {
            return null;
        }

        $next = $group->activeTasks->first();

        if ($next === null) {
            return null;
        }

        return [
            'kind' => 'category_group',
            'title' => $group->name,
            'subtitle' => $next->title,
            'group_id' => $group->id,
        ];
    }

    private static function categoryPinnedTaskSuggestion(EventCategory $category): ?array
    {
        $next = $category->pinnedTasks()->active()->first();

        if ($next === null) {
            return null;
        }

        return [
            'kind' => 'task',
            'title' => $next->title,
            'task_id' => $next->id,
        ];
    }

    private static function categoryAgendaEntrySuggestion(EventCategory $category, User $user): ?array
    {
        $entry = $category->linkedAgendaEntry;

        if ($entry === null || $entry->isDoneFor($user)) {
            return null;
        }

        return [
            'kind' => 'category_agenda',
            'title' => $entry->title,
            'subtitle' => $entry->subject,
            'agenda_entry_id' => $entry->id,
        ];
    }

    private static function categoryAgendaGenericSuggestion(User $user): ?array
    {
        $open = AgendaEntry::visibleTo($user)->ofType('homework')->openFor($user)->count();

        if ($open === 0) {
            return null;
        }

        return [
            'kind' => 'agenda_generic',
            'title' => 'Hausaufgaben erledigen',
            'subtitle' => $open === 1 ? '1 offen' : "{$open} offen",
        ];
    }

    private static function categoryTextSuggestion(EventCategory $category): ?array
    {
        $text = trim((string) $category->linked_text);

        if ($text === '') {
            return null;
        }

        return [
            'kind' => 'category_text',
            'title' => $text,
        ];
    }

    /** A stable-per-(session,cycle) pick between a project's next task and another task. */
    private static function randomFallback(User $user, int $seedKey, int $cycle): ?array
    {
        $candidates = [];

        foreach (Project::forUser($user)->ordered()->with('activeTasks')->get() as $project) {
            $next = $project->activeTasks->first();

            if ($next !== null) {
                $candidates[] = [
                    'kind' => 'project',
                    'title' => $project->name,
                    'subtitle' => $next->title,
                    'project_id' => $project->id,
                ];
            }
        }

        $otherTasks = Task::forUser($user)->active()
            ->whereIn('list', ['todos', 'tasks'])
            ->where('is_today', false)
            ->boardOrdered()
            ->get();

        foreach ($otherTasks as $task) {
            $candidates[] = [
                'kind' => 'task',
                'title' => $task->title,
                'task_id' => $task->id,
            ];
        }

        if ($candidates === []) {
            return null;
        }

        // Deterministic, not mt_rand: the ring polls every 5s and re-evaluates
        // this on every request, so the pick must not jitter mid-session.
        $index = crc32($seedKey.':'.$cycle) % count($candidates);

        return $candidates[$index];
    }
}
