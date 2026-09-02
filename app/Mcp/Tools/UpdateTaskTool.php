<?php

namespace App\Mcp\Tools;

use App\Http\Resources\TaskResource;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskMutator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Partial update — rename, reschedule, toggle important/completed, move
 * between lists/project/group, flag for today. Prefer complete_task/
 * reopen_task for a plain completion toggle (they're more specific about
 * intent); this tool is for everything else, and can also toggle
 * is_completed directly if that's more convenient for a batch of changes.
 */
class UpdateTaskTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'update_task';
    }

    public function description(): string
    {
        return 'Update fields on an existing task. Only the fields you pass are changed. Setting '
            .'project_id clears group_id (and vice versa) — a task can belong to at most one of the two.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'list' => ['type' => 'string', 'enum' => Task::LISTS],
                'project_id' => ['type' => ['integer', 'null']],
                'group_id' => ['type' => ['integer', 'null']],
                'deadline' => ['type' => ['string', 'null'], 'format' => 'date'],
                'due_date' => ['type' => ['string', 'null'], 'format' => 'date'],
                'notes' => ['type' => ['string', 'null']],
                'is_important' => ['type' => 'boolean'],
                'is_completed' => ['type' => 'boolean'],
                'is_today' => ['type' => 'boolean'],
            ],
            'required' => ['id'],
        ];
    }

    public function requiredAbility(): ?string
    {
        return McpAbility::WRITE;
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $task = $this->ownedTask($user, $arguments['id'] ?? null);

        $data = Validator::make($arguments, [
            'title' => ['sometimes', 'string', 'max:255'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_important' => ['sometimes', 'boolean'],
            'is_completed' => ['sometimes', 'boolean'],
            'is_today' => ['sometimes', 'boolean'],
            'list' => ['sometimes', Rule::in(Task::LISTS)],
            'project_id' => ['sometimes', 'nullable', 'integer', Rule::exists('projects', 'id')->where('user_id', $user->id)],
            'group_id' => ['sometimes', 'nullable', 'integer', Rule::exists('task_groups', 'id')->where('user_id', $user->id)],
        ])->validate();

        $task = TaskMutator::applyUpdate($task, $user, $data);

        return (new TaskResource($task))->resolve();
    }
}
