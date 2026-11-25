<?php

namespace App\Mcp\Tools;

use App\Http\Resources\TaskResource;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;
use App\Services\Projects\ProjectEditor;
use Illuminate\Support\Facades\Validator;

class PlaceStepTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'place_step';
    }

    public function description(): string
    {
        return 'Move an open step of a project or group to a place: another stage of its project (stage_id null = no stage; a group has none) and a position among that stage\'s open steps (index counts from 0 without the step itself, default: the end). The same call serves reordering inside a stage. A "waits on" link that the move would contradict is let go and listed in move.cleared. Calling it again with the same place changes nothing.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'task_id' => ['type' => 'integer'],
                'stage_id' => ['type' => ['integer', 'null'], 'description' => 'Target stage of the same project, or null for no stage.'],
                'index' => ['type' => ['integer', 'null'], 'minimum' => 0, 'description' => 'Position in the target stage, 0 = first. Default: the end.'],
            ],
            'required' => ['task_id'],
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
        $data = Validator::make($arguments, [
            'stage_id' => ['sometimes', 'nullable', 'integer'],
            'index' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ])->validate();

        $stage = isset($data['stage_id']) ? $this->ownedStage($user, $data['stage_id']) : null;
        $move = $this->guarded(fn () => app(ProjectEditor::class)->place($step, $stage, $data['index'] ?? null));

        return [
            'step' => (new TaskResource($step->refresh()))->resolve(),
            'move' => [
                'changed' => $move->changed,
                'from_stage_id' => $move->fromStageId,
                'from_index' => $move->fromIndex,
                'to_stage_id' => $move->toStageId,
                'to_index' => $move->toIndex,
                'cleared_waits_on' => $move->cleared,
            ],
        ];
    }
}
