<?php

namespace App\Mcp\Tools;

use App\Http\Resources\TaskResource;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;
use App\Services\Projects\ProjectEditor;
use Illuminate\Support\Facades\Validator;

class AddGroupStepTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'add_group_step';
    }

    public function description(): string
    {
        return 'Add a step to the end of a task group. It goes into the group\'s own list (To-dos or Tasks).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'group_id' => ['type' => 'integer'],
                'title' => ['type' => 'string', 'description' => 'Required, max 500 characters.'],
            ],
            'required' => ['group_id', 'title'],
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
        $group = $this->ownedTaskGroup($user, $arguments['group_id'] ?? null);
        $data = Validator::make($arguments, ['title' => ['required', 'string', 'max:500']])->validate();

        $step = app(ProjectEditor::class)->addGroupStep($user, $group, trim($data['title']));

        return (new TaskResource($step->refresh()))->resolve();
    }
}
