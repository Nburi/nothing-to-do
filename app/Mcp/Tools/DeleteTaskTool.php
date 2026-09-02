<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\TaskGroup;
use App\Models\User;

/**
 * The one destructive MCP tool. Two layers of safety, since this app's usual
 * "armed double-click" UI pattern (CLAUDE.md §10) has no equivalent for a
 * headless tool call:
 *
 *   1. Requires the mcp:delete ability — a token only has it if the user
 *      explicitly checked "Löschen erlauben" when creating it (Settings),
 *      off by default. tools/list already hides this tool without it, so a
 *      write-only token's owner never even sees delete offered.
 *   2. Requires `confirm_title` to match the task's CURRENT title exactly.
 *      A model that hallucinated an id, or is acting on a stale read from
 *      earlier in the conversation, gets the real title back instead of a
 *      silent delete — the tool-call equivalent of "click it again to
 *      confirm, but only if you're actually looking at the right one".
 */
class DeleteTaskTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'delete_task';
    }

    public function description(): string
    {
        return 'PERMANENTLY delete a task. Irreversible. You must pass confirm_title matching the '
            .'task\'s exact current title (call get_task or list_tasks first if you don\'t already have '
            .'it) — a mismatch is refused rather than silently deleting the wrong thing. Always confirm '
            .'with the user before calling this; prefer complete_task if the goal is just to clear it '
            .'from view.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'confirm_title' => ['type' => 'string', 'description' => 'Must exactly match the task\'s current title.'],
            ],
            'required' => ['id', 'confirm_title'],
        ];
    }

    public function requiredAbility(): ?string
    {
        return McpAbility::DELETE;
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false];
    }

    public function handle(User $user, array $arguments): array
    {
        $task = $this->ownedTask($user, $arguments['id'] ?? null);
        $confirmTitle = $arguments['confirm_title'] ?? null;

        if (! is_string($confirmTitle) || $confirmTitle !== $task->title) {
            throw new McpToolExecutionException(
                "confirm_title did not match. This task's current title is: \"{$task->title}\". ".
                'Pass that exact string as confirm_title to proceed, after checking with the user.'
            );
        }

        $group = $task->group;
        $deletedId = $task->id;
        $deletedTitle = $task->title;

        $task->delete();

        if ($group instanceof TaskGroup) {
            $group->pruneIfTooSmall();
        }

        return ['deleted' => true, 'id' => $deletedId, 'title' => $deletedTitle];
    }
}
