<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;

class DeleteAgendaEntryTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'delete_agenda_entry';
    }

    public function description(): string
    {
        return 'PERMANENTLY delete an Agenda entry. If it is shared with a class it disappears for EVERY member '
            .'of that class. You must pass confirm_title matching the entry\'s exact current title. Prefer '
            .'update_agenda_entry(is_done) to just tick it off. Always confirm with the user first.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'confirm_title' => ['type' => 'string', 'description' => 'Must exactly match the entry\'s current title.'],
            ],
            'required' => ['id', 'confirm_title'],
        ];
    }

    public function requiredAbility(): ?string
    {
        return McpAbility::DELETE;
    }

    public function requiredModule(): ?string
    {
        return 'agenda';
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false];
    }

    public function handle(User $user, array $arguments): array
    {
        $entry = $this->visibleAgendaEntry($user, $arguments['id'] ?? null);
        $confirm = $arguments['confirm_title'] ?? null;

        if (! is_string($confirm) || $confirm !== $entry->title) {
            throw new McpToolExecutionException(
                "confirm_title did not match. This entry's current title is: \"{$entry->title}\"".
                ($entry->isShared() ? ' (shared with a class — deleting removes it for everyone)' : '').'. '.
                'Pass that exact string as confirm_title to proceed, after checking with the user.'
            );
        }

        $id = $entry->id;
        $title = $entry->title;
        $entry->delete();

        return ['deleted' => true, 'id' => $id, 'title' => $title];
    }
}
