<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ProjectsPayload;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\Project;
use App\Models\User;
use App\Services\Projects\ProjectEditor;
use Illuminate\Support\Facades\Validator;

class UpdateProjectTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'update_project';
    }

    public function description(): string
    {
        return 'Rename a project.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'name' => ['type' => 'string', 'description' => 'Max '.Project::NAME_MAX.' characters.']], 'required' => ['id', 'name']];
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
        $project = $this->ownedProject($user, $arguments['id'] ?? null);
        $data = Validator::make($arguments, ['name' => ['required', 'string', 'max:'.Project::NAME_MAX]])->validate();
        app(ProjectEditor::class)->rename($project, trim($data['name']));

        return ProjectsPayload::project($user, $project->refresh());
    }
}
