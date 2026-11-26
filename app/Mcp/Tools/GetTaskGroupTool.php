<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ProjectsPayload;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;

class GetTaskGroupTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'get_task_group';
    }

    public function description(): string
    {
        return 'Get one task group in full: every step (open and done) and its note cards with their #tags.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['id' => ['type' => 'integer']], 'required' => ['id']];
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        return ProjectsPayload::group($user, $this->ownedTaskGroup($user, $arguments['id'] ?? null));
    }
}
