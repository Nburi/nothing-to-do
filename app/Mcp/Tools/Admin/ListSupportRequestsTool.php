<?php

namespace App\Mcp\Tools\Admin;

use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class ListSupportRequestsTool extends AdminTool
{
    public function name(): string
    {
        return 'list_support_requests';
    }

    public function description(): string
    {
        return 'ADMIN: list feedback / support requests from all users, newest first, with a message preview. '
            .'Filter by status (open, in_progress, resolved, closed) or type. Use get_support_request for the full text.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string', 'enum' => array_keys(SupportRequest::STATUSES)],
                'type' => ['type' => 'string', 'enum' => array_keys(SupportRequest::TYPES)],
                'limit' => ['type' => 'integer', 'description' => 'Max results, default 50, max 200.'],
            ],
        ];
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $data = Validator::make($arguments, [
            'status' => ['sometimes', 'string', 'in:'.implode(',', array_keys(SupportRequest::STATUSES))],
            'type' => ['sometimes', 'string', 'in:'.implode(',', array_keys(SupportRequest::TYPES))],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ])->validate();

        $requests = SupportRequest::query()
            ->with('user:id,name')
            ->ofStatus($data['status'] ?? null)
            ->when(isset($data['type']), fn ($q) => $q->where('type', $data['type']))
            ->newestFirst()
            ->limit($data['limit'] ?? 50)
            ->get();

        return [
            'requests' => $requests->map(fn (SupportRequest $r) => [
                'id' => $r->id,
                'type' => $r->type,
                'status' => $r->status,
                'subject' => $r->subject,
                'message_preview' => str($r->message)->limit(200)->toString(),
                'from' => $r->user?->name,
                'has_response' => filled($r->response),
                'created_at' => $r->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
