<?php

namespace App\Mcp\Tools;

use App\Http\Resources\TaskResource;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\Task;
use App\Models\User;
use App\Planning\Size;
use App\Support\TaskMutator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Partial update — rename, reschedule, toggle important/completed, move
 * between lists, flag for today. Prefer complete_task/
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
        return 'Update fields on an existing task. Only the fields you pass are changed.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'list' => ['type' => 'string', 'enum' => Task::LISTS],
                'deadline' => ['type' => ['string', 'null'], 'format' => 'date'],
                'due_date' => ['type' => ['string', 'null'], 'format' => 'date'],
                'notes' => ['type' => ['string', 'null']],
                'is_important' => ['type' => 'boolean'],
                'is_completed' => ['type' => 'boolean'],
                'is_today' => ['type' => 'boolean'],
                'hard_deadline' => ['type' => ['string', 'null'], 'format' => 'date', 'description' => 'Hard deadline (YYYY-MM-DD). Same as deadline; wins if both are given.'],
                'soft_deadline' => ['type' => ['string', 'null'], 'format' => 'date', 'description' => '"Finished by" (YYYY-MM-DD). Same as due_date; wins if both are given.'],
                'size' => ['type' => ['string', 'null'], 'enum' => ['quick', 'short', 'long', 'block', null], 'description' => 'In 25 min sessions: quick (several per session), short (one), long (2-3), block (4+).'],
                'area_id' => ['type' => ['integer', 'null'], 'description' => 'One of the user\'s areas.'],
                'first_step' => ['type' => ['string', 'null'], 'description' => 'Where to start ("Start with: …"), max 255 characters.'],
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
            'title' => ['sometimes', 'string', 'max:500'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_important' => ['sometimes', 'boolean'],
            'is_completed' => ['sometimes', 'boolean'],
            'is_today' => ['sometimes', 'boolean'],
            'list' => ['sometimes', Rule::in(Task::LISTS)],
            'hard_deadline' => ['sometimes', 'nullable', 'date'],
            'soft_deadline' => ['sometimes', 'nullable', 'date'],
            'size' => ['sometimes', 'nullable', Rule::enum(Size::class)],
            'area_id' => ['sometimes', 'nullable', 'integer', Rule::exists('areas', 'id')->where('user_id', $user->id)],
            'first_step' => ['sometimes', 'nullable', 'string', 'max:255'],
        ])->validate();

        $task = TaskMutator::applyUpdate($task, $user, $data);
        $task = TaskMutator::applyRefactorFields($task, $data);

        return (new TaskResource($task))->resolve();
    }
}
