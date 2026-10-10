<?php

namespace App\Mcp\Tools;

use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\User;

class GetAgendaEntryTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'get_agenda_entry';
    }

    public function description(): string
    {
        return 'Get one Agenda entry with its full notes and — for an entry shared with a class — the user\'s '
            .'own private note on it (only they ever see that one).';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'integer']],
            'required' => ['id'],
        ];
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
        $entry = $this->visibleAgendaEntry($user, $arguments['id'] ?? null);

        return AgendaEntryPresenter::full($entry, $user);
    }
}
