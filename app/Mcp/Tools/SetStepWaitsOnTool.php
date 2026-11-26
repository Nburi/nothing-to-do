<?php

namespace App\Mcp\Tools;

use App\Http\Resources\TaskResource;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;
use App\Services\Projects\ProjectEditor;
use Illuminate\Support\Facades\Validator;

class SetStepWaitsOnTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'set_step_waits_on';
    }

    public function description(): string
    {
        return 'Make a step of a project wait on one other step of the same project ("wartet auf"), or pass waits_on_id null to let it go. A waiting step is not shown on the board and not suggested until the other one is done. Two steps may not wait on each other, directly or round a chain.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'task_id' => ['type' => 'integer'],
                'waits_on_id' => ['type' => ['integer', 'null']],
            ],
            'required' => ['task_id', 'waits_on_id'],
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
        $step = $this->ownedTask($user, $arguments['task_id'] ?? null);
        $data = Validator::make($arguments, ['waits_on_id' => ['present', 'nullable', 'integer']])->validate();

        $on = $data['waits_on_id'] === null ? null : $this->ownedTask($user, $data['waits_on_id']);
        $this->guarded(fn () => app(ProjectEditor::class)->setWaitsOn($step, $on));

        return (new TaskResource($step->refresh()))->resolve();
    }
}
