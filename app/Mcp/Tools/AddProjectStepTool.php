<?php

namespace App\Mcp\Tools;

use App\Http\Resources\TaskResource;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\Task;
use App\Models\User;
use App\Services\Projects\ProjectEditor;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AddProjectStepTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'add_project_step';
    }

    public function description(): string
    {
        return 'Add a step to a project, at the end of a stage (or without a stage). A step is a normal task: set size, deadlines, area and so on afterwards with update_task. It goes into To-dos or Tasks, never into the Inbox.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => ['type' => 'integer'],
                'title' => ['type' => 'string', 'description' => 'Required, max 500 characters.'],
                'stage_id' => ['type' => ['integer', 'null'], 'description' => 'A stage of this project. Default: no stage.'],
                'list' => ['type' => 'string', 'enum' => Task::WORK_LISTS, 'description' => 'Default "tasks".'],
            ],
            'required' => ['project_id', 'title'],
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
        $project = $this->ownedProject($user, $arguments['project_id'] ?? null);
        $data = Validator::make($arguments, [
            'title' => ['required', 'string', 'max:500'],
            'stage_id' => ['sometimes', 'nullable', 'integer'],
            'list' => ['sometimes', Rule::in(Task::WORK_LISTS)],
        ])->validate();

        $stage = isset($data['stage_id']) ? $this->ownedStage($user, $data['stage_id']) : null;
        $step = $this->guarded(fn () => app(ProjectEditor::class)->addProjectStep($user, $project, $stage, trim($data['title']), $data['list'] ?? 'tasks'));

        return (new TaskResource($step->refresh()))->resolve();
    }
}
