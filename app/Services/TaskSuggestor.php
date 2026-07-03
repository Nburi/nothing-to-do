<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

/**
 * Picks "what to work on" for a Pomodoro work session. Tiered by the
 * session's cycle number (PomodoroCycle's 1-based work-cycle count, scoped
 * to one focus run — see ScheduleEvent::pomodoroPhaseNow()):
 *
 *   1. Cycle 1            → a generic nudge to clear the ToDos list.
 *   2. Any cycle          → the top active "today" task (board order).
 *   3. Fallback           → a project's next task or another active
 *                            todos/tasks-list task, picked deterministically
 *                            (stable across the header ring's 5s poll) from
 *                            a seed tied to the session + cycle.
 *
 * Falls through tiers whenever one has nothing to offer, so an empty ToDos
 * list on cycle 1 doesn't produce an empty suggestion.
 */
class TaskSuggestor
{
    /**
     * Returns null, or one of:
     *   ['kind' => 'todos', 'title' => string, 'subtitle' => string]
     *   ['kind' => 'task', 'title' => string, 'task_id' => int]
     *   ['kind' => 'project', 'title' => string, 'subtitle' => string, 'project_id' => int]
     */
    public static function suggest(User $user, int $cycle, int $seedKey): ?array
    {
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
