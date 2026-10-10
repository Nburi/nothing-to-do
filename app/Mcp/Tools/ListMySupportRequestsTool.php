<?php

namespace App\Mcp\Tools;

use App\Mcp\McpTool;
use App\Models\SupportRequest;
use App\Models\User;

class ListMySupportRequestsTool extends McpTool
{
    public function name(): string
    {
        return 'list_my_support_requests';
    }

    public function description(): string
    {
        return 'The feedback / support requests this user has sent to the nothing-to-do team, newest first, with '
            .'their status (Offen, In Bearbeitung, Erledigt, Geschlossen) and the team\'s answer once there is one. '
            .'Only the user\'s own requests are ever shown.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) []];
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        return [
            'requests' => $user->supportRequests()->newestFirst()->get()->map(fn (SupportRequest $r) => [
                'id' => $r->id,
                'type' => $r->type,
                'subject' => $r->subject,
                'message' => $r->message,
                'status' => $r->status,
                'status_label' => $r->statusMeta()['label'],
                'response' => $r->response,
                'created_at' => $r->created_at->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
