<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ProjectsPayload;
use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\TaskGroup;
use App\Models\User;
use App\Services\Projects\ProjectEditor;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateTaskGroupTool extends McpTool
{
    public function name(): string
    {
        return 'create_task_group';
    }

    public function description(): string
    {
        return 'Create a task group: a small bundle of steps that all live in one list (To-dos or Tasks); the board shows its next two. Add steps with add_group_step. Moving existing board tasks into a group is not possible through the API yet, so task_ids is refused.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Required, max '.TaskGroup::NAME_MAX.' characters.'],
                'list' => ['type' => 'string', 'enum' => TaskGroup::LISTS, 'description' => 'The list its steps live in. Default "tasks".'],
                'steps' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Titles of steps to add right away (max 500 characters each).'],
            ],
            'required' => ['name'],
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
        if (isset($arguments['task_ids'])) {
            throw new McpToolExecutionException('task_ids is no longer supported: create the group with "steps" (titles) and add more with add_group_step.');
        }

        $data = Validator::make($arguments, [
            'name' => ['required', 'string', 'max:'.TaskGroup::NAME_MAX],
            'list' => ['sometimes', Rule::in(TaskGroup::LISTS)],
            'steps' => ['sometimes', 'array', 'max:50'],
            'steps.*' => ['required', 'string', 'max:500'],
        ])->validate();

        $editor = app(ProjectEditor::class);
        $group = $editor->createGroup($user, trim($data['name']), $data['list'] ?? 'tasks');
        foreach ($data['steps'] ?? [] as $title) {
            $editor->addGroupStep($user, $group, trim($title));
        }

        return ProjectsPayload::group($user, $group);
    }
}
