<?php

namespace App\Mcp\Tools;

use App\Mcp\McpTool;
use App\Models\User;

/** The classes this user belongs to. Never exposes the invite code. */
class ListAgendaSpacesTool extends McpTool
{
    public function name(): string
    {
        return 'list_agenda_spaces';
    }

    public function description(): string
    {
        return 'List the classes (shared Agenda spaces) this user belongs to, to interpret agenda_space_id on '
            .'entries or to filter list_agenda_entries. Invite codes are never shown.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) []];
    }

    public function requiredModule(): ?string
    {
        return 'agenda';
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $spaces = $user->agendaSpaces()->withCount('members')->ordered()->get();

        return [
            'spaces' => $spaces->map(fn ($space) => [
                'id' => $space->id,
                'name' => $space->name,
                'members_count' => (int) $space->members_count,
                'is_owner' => $space->owner_id === $user->id,
            ])->values()->all(),
        ];
    }
}
