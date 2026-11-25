<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ProjectsPayload;
use App\Mcp\McpTool;
use App\Models\User;

class ListProjectsTool extends McpTool
{
    public function name(): string
    {
        return 'list_projects';
    }

    public function description(): string
    {
        return 'List this user\'s projects as the board shows them: each with its current stage, the progress of that stage, the next two steps and how many more are open. Empty projects are listed too. get_project returns one in full.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) []];
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        return ['projects' => ProjectsPayload::bundles($user, 'project')];
    }
}
