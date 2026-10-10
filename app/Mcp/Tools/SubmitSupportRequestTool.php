<?php

namespace App\Mcp\Tools;

use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SubmitSupportRequestTool extends McpTool
{
    public function name(): string
    {
        return 'submit_support_request';
    }

    public function description(): string
    {
        return 'Send feedback or a support request to the nothing-to-do team (they get a push notification). This '
            .'contacts real people on the user\'s behalf: ONLY call it when the user has asked to send it, and show '
            .'them the subject and message first. type: "feedback" or "support".';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string', 'enum' => array_keys(SupportRequest::TYPES), 'description' => 'Default "feedback".'],
                'subject' => ['type' => 'string', 'description' => 'Required, max 255.'],
                'message' => ['type' => 'string', 'description' => 'Required, max 5000.'],
            ],
            'required' => ['subject', 'message'],
        ];
    }

    public function requiredAbility(): ?string
    {
        return McpAbility::WRITE;
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false, 'openWorldHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $data = Validator::make($arguments, [
            'type' => ['sometimes', Rule::in(array_keys(SupportRequest::TYPES))],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ])->validate();

        $request = $user->supportRequests()->create([
            'type' => $data['type'] ?? SupportRequest::DEFAULT_TYPE,
            'subject' => trim($data['subject']),
            'message' => trim($data['message']),
            'status' => SupportRequest::DEFAULT_STATUS,
        ]);

        return ['sent' => true, 'id' => $request->id, 'type' => $request->type, 'subject' => $request->subject, 'status' => $request->status];
    }
}
