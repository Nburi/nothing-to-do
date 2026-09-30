<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class AnswerSupportRequestTool extends AdminTool
{
    public function name(): string
    {
        return 'answer_support_request';
    }

    public function description(): string
    {
        return 'ADMIN: answer a support request and/or change its status. Saving a non-empty response is seen by '
            .'the submitter immediately and sends them a push notification, so show the admin your draft and get '
            .'their OK before calling this. Pass at least one of response / status.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'response' => ['type' => 'string', 'description' => 'The answer shown to the submitter. Replaces an existing response.'],
                'status' => ['type' => 'string', 'enum' => array_keys(SupportRequest::STATUSES)],
            ],
            'required' => ['id'],
        ];
    }

    public function requiredAbility(): ?string
    {
        return McpAbility::WRITE;
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false];
    }

    public function handle(User $user, array $arguments): array
    {
        $request = $this->supportRequest($arguments['id'] ?? null);

        $data = Validator::make($arguments, [
            'response' => ['sometimes', 'string', 'max:5000'],
            'status' => ['sometimes', 'string', 'in:'.implode(',', array_keys(SupportRequest::STATUSES))],
        ])->validate();

        $response = isset($data['response']) ? trim($data['response']) : null;

        if ($response === null && ! isset($data['status'])) {
            throw new McpToolExecutionException('Pass at least one of response or status.');
        }
        if ($response === '') {
            throw new McpToolExecutionException('response must not be empty.');
        }

        $attributes = [];
        if ($response !== null) {
            $attributes['response'] = $response;
            $attributes['responded_by'] = $user->id;
        }
        if (isset($data['status'])) {
            $attributes['status'] = $data['status'];
        }

        $request->update($attributes);

        return [
            'id' => $request->id,
            'status' => $request->status,
            'status_label' => $request->statusLabel(),
            'response' => $request->response,
        ];
    }
}
