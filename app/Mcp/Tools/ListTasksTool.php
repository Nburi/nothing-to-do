<?php

namespace App\Mcp\Tools;

use App\Http\Resources\TaskResource;
use App\Mcp\McpTool;
use App\Models\Task;
use App\Models\User;
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
                'limit' => ['type' => 'integer', 'description' => 'Max results, default 50, max 200.'],
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
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
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

        $tasks = $query->boardOrdered()->limit($data['limit'] ?? 50)->get();

        return [
            'count' => $tasks->count(),
            'tasks' => TaskResource::collection($tasks)->resolve(),
        ];
    }
}
