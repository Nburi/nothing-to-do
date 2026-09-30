<?php

namespace App\Mcp\Tools\Admin;

use App\Models\User;

class GetSupportRequestTool extends AdminTool
{
    public function name(): string
    {
        return 'get_support_request';
    }

    public function description(): string
    {
        return 'ADMIN: read one feedback / support request in full — message, status and any existing response.';
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
        $r = $this->supportRequest($arguments['id'] ?? null);

        return [
            'id' => $r->id,
            'type' => $r->type,
            'status' => $r->status,
            'subject' => $r->subject,
            'message' => $r->message,
            'from' => $r->user?->name,
            'response' => $r->response,
            'created_at' => $r->created_at?->toIso8601String(),
            'updated_at' => $r->updated_at?->toIso8601String(),
        ];
    }
}
