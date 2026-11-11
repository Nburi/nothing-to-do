<?php

namespace App\Mcp\Tools;

use App\Http\Resources\TaskResource;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\Task;
use App\Models\User;
use App\Planning\Size;
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
        return 'Add a new task to the Inbox, or straight into one of the board lists.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string', 'description' => 'Required, max 500 characters.'],
                'list' => ['type' => 'string', 'enum' => Task::LISTS, 'description' => 'Default "inbox". "later" is the Later box.'],
                'deadline' => ['type' => 'string', 'format' => 'date', 'description' => 'Hard, externally-imposed date (YYYY-MM-DD).'],
                'due_date' => ['type' => 'string', 'format' => 'date', 'description' => 'Soft, self-imposed date (YYYY-MM-DD).'],
                'notes' => ['type' => 'string', 'description' => 'Markdown source, max 5000 characters.'],
                'is_important' => ['type' => 'boolean'],
                'is_today' => ['type' => 'boolean', 'description' => 'Flag for today. Only takes effect for a todos/tasks-list task.'],
                'hard_deadline' => ['type' => ['string', 'null'], 'format' => 'date', 'description' => 'Hard deadline (YYYY-MM-DD). Same as deadline; wins if both are given.'],
                'soft_deadline' => ['type' => ['string', 'null'], 'format' => 'date', 'description' => '"Finished by" (YYYY-MM-DD). Same as due_date; wins if both are given.'],
                'size' => ['type' => ['string', 'null'], 'enum' => ['quick', 'short', 'long', 'block', null], 'description' => 'In 25 min sessions: quick (several per session), short (one), long (2-3), block (4+).'],
                'area_id' => ['type' => ['integer', 'null'], 'description' => 'One of the user\'s areas.'],
                'first_step' => ['type' => ['string', 'null'], 'description' => 'Where to start ("Start with: …"), max 255 characters.'],
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
            'title' => ['required', 'string', 'max:500'],
            'list' => ['sometimes', Rule::in(Task::LISTS)],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_important' => ['sometimes', 'boolean'],
            'is_today' => ['sometimes', 'boolean'],
            'hard_deadline' => ['sometimes', 'nullable', 'date'],
            'soft_deadline' => ['sometimes', 'nullable', 'date'],
            'size' => ['sometimes', 'nullable', Rule::enum(Size::class)],
            'area_id' => ['sometimes', 'nullable', 'integer', Rule::exists('areas', 'id')->where('user_id', $user->id)],
            'first_step' => ['sometimes', 'nullable', 'string', 'max:255'],
        ])->validate();

        $task = $user->tasks()->create(TaskMutator::attributesForCreate($data));
        $task = TaskMutator::applyRefactorFields($task, $data);

        if (($data['is_today'] ?? false) && in_array($task->list, Task::TODAY_LISTS, true)) {
            $task->update([
                'is_today' => true,
                'today_date' => $task->todayDateFor(true, $user->localToday()),
            ]);
        }

        return (new TaskResource($task->fresh()))->resolve();
    }
}
