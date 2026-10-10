<?php

namespace App\Mcp\Tools;

use App\Http\Resources\TaskGroupResource;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskMutator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** A group only exists together with its tasks (an empty group has no reason to exist). */
class CreateTaskGroupTool extends McpTool
{
    public function name(): string
    {
        return 'create_task_group';
    }

    public function description(): string
    {
        return 'Bundle existing board tasks into a new task group (the middle size between a single task and '
            .'a project — e.g. one presentation, rearranging a room). Needs at least one task (a group that '
            .'later shrinks below two tasks dissolves itself). Tasks that belong to a project cannot be '
            .'grouped. Add notes with add_group_note.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Required, max 255 characters.'],
                'task_ids' => ['type' => 'array', 'minItems' => 1, 'items' => ['type' => 'integer'], 'description' => 'Board tasks to put into the group (not project tasks).'],
            ],
            'required' => ['name', 'task_ids'],
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
            'name' => ['required', 'string', 'max:255'],
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['integer', 'distinct', Rule::exists('tasks', 'id')->where('user_id', $user->id)->whereNull('project_id')],
        ])->validate();

        $group = DB::transaction(function () use ($user, $data) {
            $group = $user->taskGroups()->create(['name' => trim($data['name']), 'sort_order' => 0]);

            foreach ($data['task_ids'] as $taskId) {
                TaskMutator::applyUpdate(Task::forUser($user)->findOrFail($taskId), $user, ['group_id' => $group->id]);
            }

            return $group;
        });

        return (new TaskGroupResource($user->taskGroups()->with('tasks')->findOrFail($group->id)))->resolve();
    }
}
