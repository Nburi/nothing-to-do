<?php

namespace App\Services;

use App\Models\EventCategory;
use App\Models\Task;
use App\Models\User;

/**
 * Picks "what to work on" for a Pomodoro work session. Tiered:
 *
 *   2. Event task link    → the running session's *own* schedule entry has one
 *                            or more tasks bound directly to it (ScheduleEvent
 *                            ::linkedTasks) — more specific than its
 *                            category's link, so the next open one wins for
 *                            this occurrence.
 *   3. Category link      → the running session's category's linked
 *                            pinned tasks/free text, if the category has one
 *                            (see EventCategory::$task_source). Applies on
 *                            every cycle of that session, not just the first.
 *   4. Cycle 1            → a generic nudge to clear the ToDos list.
 *   5. Any cycle          → the top active "today" task (board order).
 *   6. Fallback           → another active todos/tasks-list task, picked deterministically
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
     *   ['kind' => 'todos'|'category_text', 'title' => string, 'subtitle' => ?string]
     *   ['kind' => 'task', 'title' => string, 'task_id' => int]
     */
    public static function suggest(User $user, int $cycle, int $seedKey, ?EventCategory $category = null, ?Task $linkedTask = null): ?array
    {
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

            // Linked source is empty, deleted — fall through.
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

        $todayTask = Task::forUser($user)->active()
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
     * null when there is nothing to count (no link, or a free-text link). Used both above (indirectly,
     * via categorySuggestion) and by TaskBoard::linkedSourceNotice() to detect
     * the exact moment a linked list is cleared during a running session.
     */
    public static function linkedSourceRemainingCount(EventCategory $category, User $user): ?int
    {
        return match ($category->task_source) {
            'tasks' => $category->pinnedTasks()->active()->count(),
            default => null, // 'text', or no source set
        };
    }

    /** Dispatches to the one branch matching the category's current task_source. */
    private static function categorySuggestion(EventCategory $category, User $user): ?array
    {
        return match ($category->task_source) {
            'tasks' => self::categoryPinnedTaskSuggestion($category),
            'text' => self::categoryTextSuggestion($category),
            default => null,
        };
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

    /** A stable-per-(session,cycle) pick among the user's other open tasks. */
    private static function randomFallback(User $user, int $seedKey, int $cycle): ?array
    {
        $candidates = [];

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
