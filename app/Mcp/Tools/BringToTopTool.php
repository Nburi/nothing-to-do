<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\User;
use App\Services\Planning\BoardOrder;
use Illuminate\Support\Facades\Validator;

/** "Nach oben holen" (api parity): the entry becomes the first of the whole board and wakes up if it rested. */
class BringToTopTool extends McpTool
{
    public function name(): string
    {
        return 'bring_to_top';
    }

    public function description(): string
    {
        return 'Bring a task, project or group to the very top of the board order ("Nach oben holen"). A project or group card that rested (the board shows only the first two cards) wakes up and the lowest awake card rests instead. ref is "task:12", "project:3" or "group:4" (the ref field of projects and groups).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['ref' => ['type' => 'string', 'description' => 'task:<id>, project:<id> or group:<id>']],
            'required' => ['ref'],
        ];
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
        $data = Validator::make($arguments, ['ref' => ['required', 'string', 'regex:/^(task|project|group):\d+$/']])->validate();

        if (! app(BoardOrder::class)->toTop($user, $data['ref'])) {
            throw new McpToolExecutionException("No open {$data['ref']} found for this account.");
        }

        return ['ref' => $data['ref'], 'at_top' => true];
    }
}
