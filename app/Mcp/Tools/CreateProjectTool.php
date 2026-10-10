<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ProjectResource;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class CreateProjectTool extends McpTool
{
    public function name(): string
    {
        return 'create_project';
    }

    public function description(): string
    {
        return 'Create a project (a container for non-urgent, multi-part work). Add tasks to it afterwards '
            .'with create_task(project_id) or update_task(project_id).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Required, max 255 characters.'],
                'deadline' => ['type' => 'string', 'format' => 'date', 'description' => 'YYYY-MM-DD.'],
                'external_url' => ['type' => 'string', 'description' => 'Link to Jira/GitHub/Linear/…, must be a full URL.'],
                'brainstorm' => ['type' => 'string', 'description' => 'Markdown scratchpad, max 50000 characters.'],
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
        $data = Validator::make($arguments, [
            'name' => ['required', 'string', 'max:255'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'external_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'brainstorm' => ['sometimes', 'nullable', 'string', 'max:50000'],
        ])->validate();

        $brainstorm = trim((string) ($data['brainstorm'] ?? ''));

        $project = $user->projects()->create([
            'name' => trim($data['name']),
            'deadline' => $data['deadline'] ?? null,
            'external_url' => isset($data['external_url']) ? trim($data['external_url']) : null,
            'brainstorm' => $brainstorm !== '' ? $brainstorm : null,
            'sort_order' => 0,
        ]);

        return (new ProjectResource($project->fresh()))->resolve();
    }
}
