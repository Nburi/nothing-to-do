<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ProjectsPayload;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\ProjectStage;
use App\Models\User;
use App\Services\Projects\ProjectEditor;
use Illuminate\Support\Facades\Validator;

class UpdateProjectStageTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'update_project_stage';
    }

    public function description(): string
    {
        return 'Rename a stage, change its target date (null clears it) or move it in the roadmap (position, 0 = first). Only the fields you pass change. Moving a stage behind another lets go of "waits on" links it would contradict (move.cleared_waits_on).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string', 'description' => 'Max '.ProjectStage::NAME_MAX.' characters.'],
                'target_date' => ['type' => ['string', 'null'], 'format' => 'date'],
                'position' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Place in the roadmap, 0 = first (clamped).'],
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
        $stage = $this->ownedStage($user, $arguments['id'] ?? null);
        $data = Validator::make($arguments, [
            'name' => ['sometimes', 'required', 'string', 'max:'.ProjectStage::NAME_MAX],
            'target_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ])->validate();

        app(ProjectEditor::class)->updateStage(
            $stage,
            isset($data['name']) ? trim($data['name']) : $stage->name,
            array_key_exists('target_date', $data) ? $data['target_date'] : $stage->target_date?->toDateString(),
        );

        $payload = ProjectsPayload::stage($stage->refresh());
        if (isset($data['position'])) {
            $move = $this->guarded(fn () => app(ProjectEditor::class)->moveStage($stage, (int) $data['position']));
            $payload = ProjectsPayload::stage($stage->refresh()) + ['move' => [
                'changed' => $move->changed,
                'from_index' => $move->fromIndex,
                'to_index' => $move->toIndex,
                'cleared_waits_on' => $move->cleared,
            ]];
        }

        return $payload;
    }
}
