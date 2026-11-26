<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ProjectsPayload;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;

class GetProjectTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'get_project';
    }

    public function description(): string
    {
        return 'Get one project in full: its stages (the roadmap, in order, each with an optional target date and its open/done counts), every step (open and done, stage by stage; a step may wait on another via waits_on_id) and its note cards with their #tags.';
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
        return ProjectsPayload::project($user, $this->ownedProject($user, $arguments['id'] ?? null));
    }
}
