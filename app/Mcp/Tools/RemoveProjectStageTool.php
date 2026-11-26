<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;
use App\Services\Projects\ProjectEditor;

class RemoveProjectStageTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'remove_project_stage';
    }

    public function description(): string
    {
        return 'Remove a stage from the roadmap. Its steps are not deleted: they stay in the project without a stage, and its note cards go back to "Lose". Pass confirm_name matching the stage\'s exact current name.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'confirm_name' => ['type' => 'string', 'description' => 'Must exactly match the stage\'s current name.'],
            ],
            'required' => ['id', 'confirm_name'],
        ];
    }

    public function requiredAbility(): ?string
    {
        return McpAbility::WRITE;
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false];
    }

    public function handle(User $user, array $arguments): array
    {
        $stage = $this->ownedStage($user, $arguments['id'] ?? null);

        if (($arguments['confirm_name'] ?? null) !== $stage->name) {
            throw new McpToolExecutionException("confirm_name did not match. This stage is called \"{$stage->name}\". Pass that exact name after checking with the user.");
        }

        $id = $stage->id;
        app(ProjectEditor::class)->removeStage($stage);

        return ['removed' => true, 'id' => $id];
    }
}
