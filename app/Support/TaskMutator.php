<?php

namespace App\Support;

use App\Models\Task;
use App\Models\User;

/**
 * Shared task-mutation logic used by both the REST API (Api\TaskController)
 * and the MCP tools (App\Mcp\Tools\CreateTaskTool/UpdateTaskTool) — extracted
 * so the two can never independently drift on the invariants CLAUDE.md §10
 * already documents once going wrong ("A new invariant added after a feature
 * already exists needs an audit pass over that feature too"): every
 * `is_today` write must also stamp `today_date` (Task::todayDateFor()). A
 * second, parallel implementation of this logic is exactly the trap that
 * section warns about.
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
            'sort_order' => 0,
        ];

        $attributes['list'] = $data['list'] ?? 'inbox';

        return $attributes;
    }

    /**
     * Apply a partial update to $task, honoring the same invariants the REST
     * API's own update() already enforced: today_date follows is_today and a
     * list outside the Today lists clears today. Only keys present in $data
     * are touched — same "sparse update" contract as the REST API.
     *
     * @param  array<string, mixed>  $data  already validated
     */
    public static function applyUpdate(Task $task, User $user, array $data): Task
    {
        $updates = [];

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

        return $task->fresh();
    }
}
