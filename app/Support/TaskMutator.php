<?php

namespace App\Support;

use App\Models\Task;
use App\Models\User;
use App\Services\ProgressStats;

/**
 * Shared task-mutation logic used by both the REST API (Api\TaskController)
 * and the MCP tools (App\Mcp\Tools\CreateTaskTool/UpdateTaskTool) — extracted
 * so the two can never independently drift on the invariants CLAUDE.md §10
 * already documents once going wrong ("A new invariant added after a feature
 * already exists needs an audit pass over that feature too"): every
 * `is_today` write must also stamp `today_date` (Task::todayDateFor()) and
 * every `is_completed` write must run the repeat rule (Task::syncRepeat()).
 * A second, parallel implementation of this logic is exactly the trap that
 * section warns about.
 *
 * Completing a task here also runs it through ProgressStats::celebrationFor()
 * — its return value is discarded (there's no browser here to show a
 * celebration to), but the call itself is what persists a newly-reached
 * perfect day (see ProgressStats::recordOutcome()). The streak's data
 * integrity can't depend on whether the completion happened to come from a
 * surface with a UI to celebrate on.
 */
class TaskMutator
{
    /**
     * Build the create-time attribute array for a new task from already
     * validated input — the given list, or the Inbox by default.
     *
     * @param  array{title:string, list?:?string, deadline?:?string, due_date?:?string, notes?:?string, is_important?:?bool}  $data
     * @return array<string, mixed>
     */
    public static function attributesForCreate(array $data): array
    {
        $title = trim($data['title']);
        $notes = isset($data['notes']) ? trim((string) $data['notes']) : '';

        $attributes = [
            'title' => $title,
            'deadline' => $data['deadline'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'notes' => $notes !== '' ? $notes : null,
            'is_important' => $data['is_important'] ?? false,
            'repeat_rule' => $data['repeat_rule'] ?? null,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'sort_order' => 0,
        ];

        $attributes['list'] = $data['list'] ?? 'inbox';

        return $attributes;
    }

    /**
     * Apply a partial update to $task, honoring the same invariants the REST
     * API's own update() already enforced: today_date follows is_today, a
     * list outside the Today lists clears today and a completed toggle runs
     * the repeat rule. Only keys present in $data are touched — same "sparse
     * update" contract as the REST API.
     *
     * @param  array<string, mixed>  $data  already validated
     */
    public static function applyUpdate(Task $task, User $user, array $data): Task
    {
        $updates = [];

        // Captured before the write, only when actually completing (not
        // un-completing) — ProgressStats::celebrationFor() needs to know
        // where today's count stood a moment ago to detect the exact crossing.
        $willComplete = array_key_exists('is_completed', $data) && $data['is_completed'] && ! $task->is_completed;
        $beforeCount = $willComplete ? ProgressStats::todayCount($user) : null;

        if (array_key_exists('title', $data)) {
            $updates['title'] = trim($data['title']);
        }
        if (array_key_exists('deadline', $data)) {
            $updates['deadline'] = $data['deadline'];
        }
        if (array_key_exists('due_date', $data)) {
            $updates['due_date'] = $data['due_date'];
        }
        if (array_key_exists('notes', $data)) {
            $notes = $data['notes'] !== null ? trim($data['notes']) : null;
            $updates['notes'] = $notes !== '' ? $notes : null;
        }
        if (array_key_exists('is_important', $data)) {
            $updates['is_important'] = $data['is_important'];
        }
        if (array_key_exists('repeat_rule', $data)) {
            $updates['repeat_rule'] = $data['repeat_rule'];
        }
        if (array_key_exists('duration_minutes', $data)) {
            $updates['duration_minutes'] = $data['duration_minutes'];
        }
        if (array_key_exists('is_completed', $data)) {
            $updates['is_completed'] = $data['is_completed'];
            $updates['completed_at'] = $data['is_completed'] ? now() : null;
        }

        if (array_key_exists('list', $data)) {
            $updates['list'] = $data['list'];
            if (! in_array($data['list'], Task::TODAY_LISTS, true)) {
                $updates['is_today'] = false;
                $updates['today_date'] = null;
            }
        }

        if (array_key_exists('is_today', $data)) {
            $finalList = $updates['list'] ?? $task->list;

            if (in_array($finalList, Task::TODAY_LISTS, true)) {
                $updates['is_today'] = $data['is_today'];
                $updates['today_date'] = $task->todayDateFor($data['is_today'], $user->localToday());
            }
        }

        $task->update($updates);

        if (array_key_exists('is_completed', $updates)) {
            $task->syncRepeat($user, $updates['is_completed']);
        }

        if ($willComplete) {
            ProgressStats::celebrationFor($user, $task, $beforeCount);
        }

        return $task->fresh();
    }
}
