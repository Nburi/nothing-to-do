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
        return 'Rename a stage or change its target date (null clears it). Only the fields you pass change.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string', 'description' => 'Max '.ProjectStage::NAME_MAX.' characters.'],
                'target_date' => ['type' => ['string', 'null'], 'format' => 'date'],
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
        ])->validate();

        app(ProjectEditor::class)->updateStage(
            $stage,
            isset($data['name']) ? trim($data['name']) : $stage->name,
            array_key_exists('target_date', $data) ? $data['target_date'] : $stage->target_date?->toDateString(),
        );

        return ProjectsPayload::stage($stage->refresh());
    }
}
