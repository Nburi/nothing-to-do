<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ProjectsPayload;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\TaskGroup;
use App\Models\User;
use App\Services\Projects\ProjectEditor;
use Illuminate\Support\Facades\Validator;

class UpdateTaskGroupTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'update_task_group';
    }

    public function description(): string
    {
        return 'Rename a task group.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => ['id' => ['type' => 'integer'], 'name' => ['type' => 'string', 'description' => 'Max '.TaskGroup::NAME_MAX.' characters.']], 'required' => ['id', 'name']];
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
        $group = $this->ownedTaskGroup($user, $arguments['id'] ?? null);
        $data = Validator::make($arguments, ['name' => ['required', 'string', 'max:'.TaskGroup::NAME_MAX]])->validate();
        app(ProjectEditor::class)->rename($group, trim($data['name']));

        return ProjectsPayload::group($user, $group->refresh());
    }
}
