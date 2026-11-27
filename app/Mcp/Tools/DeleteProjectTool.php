<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;
use App\Services\Projects\ProjectEditor;

/** Delete a project (api parity with the project page). No step is lost; stages and note cards go. */
class DeleteProjectTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'delete_project';
    }

    public function description(): string
    {
        return 'Delete a project. Its steps are NOT deleted: every step, open or done, stays on the board in its own list as a plain task (without stage and "waits on" link). The stages and the note cards of the project are deleted and cannot be brought back. Pass confirm_name matching the project\'s exact name, and ask the user first. The result says what went.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'confirm_name' => ['type' => 'string', 'description' => 'Must exactly match the project\'s current name.'],
            ],
            'required' => ['id', 'confirm_name'],
        ];
    }

    public function requiredAbility(): ?string
    {
        return McpAbility::DELETE;
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false];
    }

    public function handle(User $user, array $arguments): array
    {
        $project = $this->ownedProject($user, $arguments['id'] ?? null);

        if (($arguments['confirm_name'] ?? null) !== $project->name) {
            throw new McpToolExecutionException(
                "confirm_name did not match. This project's current name is: \"{$project->name}\". ".
                'Pass that exact string as confirm_name to proceed, after checking with the user.'
            );
        }

        $editor = app(ProjectEditor::class);
        $impact = $editor->impact($project);
        $editor->deleteProject($project);

        return [
            'deleted' => true,
            'id' => $project->id,
            'name' => $project->name,
            'steps_kept' => $impact['open'] + $impact['done'],
            'stages_deleted' => $impact['stages'],
            'notes_deleted' => $impact['notes'],
        ];
    }
}
