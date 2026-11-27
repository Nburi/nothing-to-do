<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\AreaController;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Support\ControllerBridge;
use App\Models\User;

/** Areas (api parity): the second sort of the board, next to the categories that define work time. */
class ListAreasTool extends McpTool
{
    public function name(): string
    {
        return 'list_areas';
    }

    public function description(): string
    {
        return 'List the user\'s areas (name, colour, order). An area groups tasks by topic (area_id on a task); categories suggest areas (area_ids on create_category / update_category).';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) []];
    }

    public function requiredAbility(): ?string
    {
        return McpAbility::READ;
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        return ['areas' => ControllerBridge::call($user, AreaController::class, 'index')];
    }
}
