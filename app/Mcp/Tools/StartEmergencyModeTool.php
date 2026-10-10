<?php

namespace App\Mcp\Tools;

use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;

class StartEmergencyModeTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'start_emergency_mode';
    }

    public function description(): string
    {
        return 'Switch Notfallmodus on for one project: the board narrows to that project\'s tasks (plus anything '
            .'important) and the focus timer suggests its next task first. Open tasks without a column yet default to '
            .'"tasks"; fine-tune order and columns with arrange_emergency_tasks. Replaces a previously active '
            .'project. Nothing about the tasks changes, and end_emergency_mode undoes it completely.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['project_id' => ['type' => 'integer']], 'required' => ['project_id']];
    }

    public function requiredModule(): ?string
    {
        return 'emergency';
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
        $project = $this->ownedProject($user, $arguments['project_id'] ?? null);

        $project->tasks()->active()->whereNull('emergency_list')->update(['emergency_list' => 'tasks']);
        $user->update(['emergency_project_id' => $project->id]);

        return ['active' => true, 'project' => ['id' => $project->id, 'name' => $project->name]];
    }
}
