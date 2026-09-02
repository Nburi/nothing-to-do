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
 * `is_today` write must also stamp `today_date` (Task::todayDateFor()),
 * every `is_completed` write must sync a linked Agenda entry
 * (Task::syncLinkedAgendaEntry()), and leaving a group must prune it once
 * it's too small (TaskGroup::pruneIfTooSmall()). A second, parallel
 * implementation of this logic is exactly the trap that section warns about.
 */
class TaskMutator
{
    /**
     * Build the create-time attribute array for a new task from already
     * validated input. Mirrors the REST API's own store() resolution: an
     * explicit project_id always wins and forces list=projects; a group_id
     * (MCP-only — the REST API has no group support) wins next and forces
     * group_id set with project_id cleared; otherwise the given/default list.
     *
     * @param  array{title:string, list?:?string, project_id?:?int, group_id?:?int, deadline?:?string, due_date?:?string, notes?:?string, is_important?:?bool}  $data
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

        if (! empty($data['project_id'])) {
            $attributes['project_id'] = $data['project_id'];
            $attributes['group_id'] = null;
            $attributes['list'] = 'projects';
        } elseif (! empty($data['group_id'])) {
            $attributes['group_id'] = $data['group_id'];
            $attributes['list'] = $data['list'] ?? 'inbox';
        } else {
            $attributes['list'] = $data['list'] ?? 'inbox';
        }

        return $attributes;
    }

    /**
     * Apply a partial update to $task, honoring the same invariants the REST
     * API's own update() already enforced: today_date follows is_today,
     * an explicit project_id forces list=projects and clears today/group,
     * a group_id (MCP-only) clears project_id, a completed toggle syncs the
     * linked Agenda entry, and leaving a group prunes it if it's now too
     * small. Only keys present in $data are touched — same "sparse update"
     * contract as the REST API.
     *
     * @param  array<string, mixed>  $data  already validated
     */
    public static function applyUpdate(Task $task, User $user, array $data): Task
    {
        $previousGroup = $task->group;
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

        // Resolve the list/project/group destination together, same as the
        // edit sheet: an explicit project_id always wins over group_id/list.
        if (array_key_exists('project_id', $data)) {
            if ($data['project_id'] !== null) {
                $updates['project_id'] = $data['project_id'];
                $updates['group_id'] = null;
                $updates['list'] = 'projects';
                $updates['is_today'] = false;
                $updates['today_date'] = null;
            } else {
                $updates['project_id'] = null;
                $updates['list'] = $data['list'] ?? 'inbox';
            }
        } elseif (array_key_exists('group_id', $data)) {
            $updates['group_id'] = $data['group_id'];
            if ($data['group_id'] !== null) {
                $updates['project_id'] = null;
            }
        } elseif (array_key_exists('list', $data)) {
            $updates['list'] = $data['list'];
            $updates['project_id'] = null;
            if (! in_array($data['list'], Task::TODAY_LISTS, true)) {
                $updates['is_today'] = false;
                $updates['today_date'] = null;
            }
        }

        if (array_key_exists('is_today', $data)) {
            $finalList = $updates['list'] ?? $task->list;
            $finalProjectId = array_key_exists('project_id', $updates) ? $updates['project_id'] : $task->project_id;

            if (in_array($finalList, Task::TODAY_LISTS, true) && $finalProjectId === null) {
                $updates['is_today'] = $data['is_today'];
                $updates['today_date'] = $task->todayDateFor($data['is_today'], $user->localToday());
            }
        }

        $task->update($updates);

        if (array_key_exists('is_completed', $updates)) {
            $task->syncLinkedAgendaEntry($user, $updates['is_completed']);
        }

        if ($previousGroup !== null && $previousGroup->id !== $task->group_id) {
            $previousGroup->pruneIfTooSmall();
        }

        return $task->fresh();
    }
}
