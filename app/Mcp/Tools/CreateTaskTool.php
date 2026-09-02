<?php

namespace App\Mcp\Tools;

use App\Http\Resources\TaskResource;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskMutator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateTaskTool extends McpTool
{
    public function name(): string
    {
        return 'create_task';
    }

    public function description(): string
    {
        return 'Add a new task. Give it a project_id OR a group_id to file it straight into that project/'
            .'group instead of the board (the two are mutually exclusive — project_id wins if both are given).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string', 'description' => 'Required, max 255 characters.'],
                'list' => ['type' => 'string', 'enum' => Task::BOARD_LISTS, 'description' => 'Default "inbox".'],
                'project_id' => ['type' => 'integer'],
                'group_id' => ['type' => 'integer'],
                'deadline' => ['type' => 'string', 'format' => 'date', 'description' => 'Hard, externally-imposed date (YYYY-MM-DD).'],
                'due_date' => ['type' => 'string', 'format' => 'date', 'description' => 'Soft, self-imposed date (YYYY-MM-DD).'],
                'notes' => ['type' => 'string', 'description' => 'Markdown source, max 5000 characters.'],
                'is_important' => ['type' => 'boolean'],
                'is_today' => ['type' => 'boolean', 'description' => 'Flag for today. Only takes effect for a todos/tasks-list task with no project.'],
            ],
            'required' => ['title'],
        ];
    }

    public function requiredAbility(): ?string
    {
        return McpAbility::WRITE;
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false];
    }

    public function handle(User $user, array $arguments): array
    {
        $data = Validator::make($arguments, [
            'title' => ['required', 'string', 'max:255'],
            'list' => ['sometimes', Rule::in(Task::BOARD_LISTS)],
            'project_id' => ['sometimes', 'nullable', 'integer', Rule::exists('projects', 'id')->where('user_id', $user->id)],
            'group_id' => ['sometimes', 'nullable', 'integer', Rule::exists('task_groups', 'id')->where('user_id', $user->id)],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_important' => ['sometimes', 'boolean'],
            'is_today' => ['sometimes', 'boolean'],
        ])->validate();

        $task = $user->tasks()->create(TaskMutator::attributesForCreate($data));

        if (($data['is_today'] ?? false) && in_array($task->list, Task::TODAY_LISTS, true) && $task->project_id === null) {
            $task->update([
                'is_today' => true,
                'today_date' => $task->todayDateFor(true, $user->localToday()),
            ]);
        }

        return (new TaskResource($task->fresh()))->resolve();
    }
}
