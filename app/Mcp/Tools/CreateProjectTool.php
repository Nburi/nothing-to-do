<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ProjectsPayload;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\Project;
use App\Models\User;
use App\Services\Projects\ProjectEditor;
use Illuminate\Support\Facades\Validator;

class CreateProjectTool extends McpTool
{
    public function name(): string
    {
        return 'create_project';
    }

    public function description(): string
    {
        return 'Create a project: a bigger piece of work with stages (roadmap) and steps. Add stages with add_project_stage and steps with add_project_step. The old fields deadline, external_url and brainstorm are not part of the project any more (notes are note cards: add_note).';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['name' => ['type' => 'string', 'description' => 'Required, max '.Project::NAME_MAX.' characters.']], 'required' => ['name']];
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
        $data = Validator::make($arguments, ['name' => ['required', 'string', 'max:'.Project::NAME_MAX]])->validate();
        $project = app(ProjectEditor::class)->createProject($user, trim($data['name']));

        return ProjectsPayload::project($user, $project);
    }
}
