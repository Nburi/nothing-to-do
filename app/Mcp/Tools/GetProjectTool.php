<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ProjectResource;
use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpTool;
use App\Models\User;

class GetProjectTool extends McpTool
{
    public function name(): string
    {
        return 'get_project';
    }

    public function description(): string
    {
        return 'Get one project with its full brainstorm text (Markdown), external link, deadline and all of '
            .'its tasks (open and completed). list_projects only returns a summary.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'integer']],
            'required' => ['id'],
        ];
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $project = $user->projects()
            ->withCount(['tasks as done_count' => fn ($q) => $q->where('is_completed', true)])
            ->with('tasks')
            ->find((int) ($arguments['id'] ?? 0));

        if ($project === null) {
            throw new McpToolExecutionException('No project with id '.($arguments['id'] ?? '?').' found for this account.');
        }

        return (new ProjectResource($project))->resolve();
    }
}
