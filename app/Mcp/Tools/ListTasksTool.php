<?php

namespace App\Mcp\Tools;

use App\Http\Resources\TaskResource;
use App\Mcp\McpTool;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Raw, filterable task listing — independent of the user's active list
 * concept (see GetBoardTool for a concept-aware view). Mirrors
 * Api\TaskController::index()'s own filter shape plus group_id, which the
 * REST API doesn't support.
 */
class ListTasksTool extends McpTool
{
    public function name(): string
    {
        return 'list_tasks';
    }

    public function description(): string
    {
        return 'List this user\'s tasks (to-dos, tasks, and inbox items), with optional filters. '
            .'Only active (uncompleted) board tasks are returned by default — pass project_id or '
            .'group_id to see tasks inside a project/group, or completed=true to include finished ones.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'list' => ['type' => 'string', 'enum' => Task::LISTS, 'description' => 'Filter to one board list.'],
                'project_id' => ['type' => 'integer', 'description' => 'Only tasks inside this project.'],
                'group_id' => ['type' => 'integer', 'description' => 'Only tasks inside this task group.'],
                'is_today' => ['type' => 'boolean', 'description' => 'Only tasks flagged for today.'],
                'is_important' => ['type' => 'boolean'],
                'completed' => ['type' => 'boolean', 'description' => 'Include completed tasks too. Default false.'],
                'search' => ['type' => 'string', 'description' => 'Case-insensitive substring match on the title.'],
                'due_on_or_before' => ['type' => 'string', 'format' => 'date', 'description' => 'Only tasks whose effective date (deadline, else due_date) is on or before this day (YYYY-MM-DD). Undated tasks are excluded.'],
                'due_on_or_after' => ['type' => 'string', 'format' => 'date', 'description' => 'Only tasks whose effective date is on or after this day. Undated tasks are excluded.'],
                'overdue' => ['type' => 'boolean', 'description' => 'true = only tasks whose effective date is before today (in the user\'s timezone).'],
                'without_duration' => ['type' => 'boolean', 'description' => 'true = only tasks that have no duration estimate yet.'],
                'repeating' => ['type' => 'boolean', 'description' => 'true = only recurring tasks, false = only non-recurring.'],
                'limit' => ['type' => 'integer', 'description' => 'Max results, default 50, max 200.'],
                'offset' => ['type' => 'integer', 'description' => 'Skip this many results (pagination), default 0.'],
            ],
        ];
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $data = Validator::make($arguments, [
            'list' => ['sometimes', Rule::in(Task::LISTS)],
            'project_id' => ['sometimes', 'integer'],
            'group_id' => ['sometimes', 'integer'],
            'is_today' => ['sometimes', 'boolean'],
            'is_important' => ['sometimes', 'boolean'],
            'completed' => ['sometimes', 'boolean'],
            'search' => ['sometimes', 'string', 'max:255'],
            'due_on_or_before' => ['sometimes', 'date'],
            'due_on_or_after' => ['sometimes', 'date'],
            'overdue' => ['sometimes', 'boolean'],
            'without_duration' => ['sometimes', 'boolean'],
            'repeating' => ['sometimes', 'boolean'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ])->validate();

        $query = $user->tasks()->newQuery();

        if (isset($data['project_id'])) {
            $query->where('project_id', $data['project_id']);
        } elseif (isset($data['group_id'])) {
            $query->where('group_id', $data['group_id']);
        } else {
            $query->onBoard();
        }

        if (isset($data['list'])) {
            $query->inList($data['list']);
        }
        if (isset($data['is_today'])) {
            $query->where('is_today', $data['is_today']);
        }
        if (isset($data['is_important'])) {
            $query->where('is_important', $data['is_important']);
        }
        if (! ($data['completed'] ?? false)) {
            $query->where('is_completed', false);
        }
        if (isset($data['search']) && trim($data['search']) !== '') {
            $query->where('title', 'like', '%'.trim($data['search']).'%');
        }

        // DATE(...) because a 'date'-cast column is stored with a time part (CLAUDE.md §10).
        if (isset($data['due_on_or_before'])) {
            $query->whereRaw('DATE(COALESCE(deadline, due_date)) <= ?', [Carbon::parse($data['due_on_or_before'])->toDateString()]);
        }
        if (isset($data['due_on_or_after'])) {
            $query->whereRaw('DATE(COALESCE(deadline, due_date)) >= ?', [Carbon::parse($data['due_on_or_after'])->toDateString()]);
        }
        if (($data['overdue'] ?? false) === true) {
            $query->whereRaw('DATE(COALESCE(deadline, due_date)) < ?', [$user->localToday()->toDateString()]);
        }
        if (($data['without_duration'] ?? false) === true) {
            $query->whereNull('duration_minutes');
        }
        if (isset($data['repeating'])) {
            $data['repeating'] ? $query->whereNotNull('repeat_rule') : $query->whereNull('repeat_rule');
        }

        $total = (clone $query)->count();
        $tasks = $query->boardOrdered()->offset($data['offset'] ?? 0)->limit($data['limit'] ?? 50)->get();

        return [
            'total' => $total,
            'count' => $tasks->count(),
            'tasks' => TaskResource::collection($tasks)->resolve(),
        ];
    }
}
