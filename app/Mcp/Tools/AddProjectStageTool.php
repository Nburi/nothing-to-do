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

class AddProjectStageTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'add_project_stage';
    }

    public function description(): string
    {
        return 'Add a stage ("Etappe") to the end of a project\'s roadmap. Stage 2 waits on stage 1: the board only shows the steps of the first stage that still has open steps. The target date is a note, it does not reorder or warn.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => ['type' => 'integer'],
                'name' => ['type' => 'string', 'description' => 'Max '.ProjectStage::NAME_MAX.' characters.'],
                'target_date' => ['type' => ['string', 'null'], 'format' => 'date', 'description' => 'Optional, YYYY-MM-DD.'],
            ],
            'required' => ['project_id', 'name'],
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
            'name' => ['required', 'string', 'max:'.ProjectStage::NAME_MAX],
            'target_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ])->validate();

        $stage = app(ProjectEditor::class)->addStage($project, trim($data['name']), $data['target_date'] ?? null);

        return ProjectsPayload::stage($stage->refresh());
    }
}
