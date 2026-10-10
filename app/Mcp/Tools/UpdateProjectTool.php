<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ProjectResource;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;
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
        return 'Update a project. Only the fields you pass are changed; pass null to clear deadline, '
            .'external_url or brainstorm. Note that brainstorm REPLACES the whole text — read it with '
            .'get_project first when you want to append.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'deadline' => ['type' => ['string', 'null'], 'format' => 'date'],
                'external_url' => ['type' => ['string', 'null']],
                'brainstorm' => ['type' => ['string', 'null'], 'description' => 'Markdown, max 50000 characters.'],
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
        $project = $this->ownedProject($user, $arguments['id'] ?? null);

        $data = Validator::make($arguments, [
            'name' => ['sometimes', 'string', 'max:255'],
            'deadline' => ['sometimes', 'nullable', 'date'],
            'external_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'brainstorm' => ['sometimes', 'nullable', 'string', 'max:50000'],
        ])->validate();

        $updates = [];

        if (array_key_exists('name', $data)) {
            $updates['name'] = trim($data['name']);
        }
        if (array_key_exists('deadline', $data)) {
            $updates['deadline'] = $data['deadline'];
        }
        if (array_key_exists('external_url', $data)) {
            $updates['external_url'] = $data['external_url'] !== null ? trim($data['external_url']) : null;
        }
        if (array_key_exists('brainstorm', $data)) {
            $text = trim((string) $data['brainstorm']);
            $updates['brainstorm'] = $text !== '' ? $text : null;
        }

        $project->update($updates);

        return (new ProjectResource($project->fresh()))->resolve();
    }
}
