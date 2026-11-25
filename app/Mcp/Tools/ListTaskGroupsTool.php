<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ProjectsPayload;
use App\Mcp\McpTool;
use App\Models\User;

class ListTaskGroupsTool extends McpTool
{
    public function name(): string
    {
        return 'list_task_groups';
    }

    public function description(): string
    {
        return 'List this user\'s task groups (small bundles of steps in one list, To-dos or Tasks) with progress, the next two steps and how many more are open.';
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
        return ['task_groups' => ProjectsPayload::bundles($user, 'group')];
    }
}
