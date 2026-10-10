<?php

namespace App\Mcp\Tools;

use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\User;

class EndEmergencyModeTool extends McpTool
{
    public function name(): string
    {
        return 'end_emergency_mode';
    }

    public function description(): string
    {
        return 'Switch Notfallmodus off. The board returns to normal; the project\'s tasks and their order stay exactly '
            .'as arranged, so starting it again later picks up where it was.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) []];
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
        $user->update(['emergency_project_id' => null]);

        return ['active' => false];
    }
}
