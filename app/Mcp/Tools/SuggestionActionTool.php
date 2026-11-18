<?php

namespace App\Mcp\Tools;

use App\Http\Resources\PlanningPayload;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\User;
use App\Planning\Battery;
use App\Services\Planning\Suggestions;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Refactor step 1.5: "Another" and the battery check-in (R1, G4). */
class SuggestionActionTool extends McpTool
{
    public function name(): string
    {
        return 'change_suggestion';
    }

    public function description(): string
    {
        return 'Change tonight\'s suggestion: action "another" skips the current pick, action "battery" '
            .'sets tonight\'s energy (low, okay, good; null = skipped, counts as okay). Returns the new suggestion.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'action' => ['type' => 'string', 'enum' => ['another', 'battery']],
                'battery' => ['type' => ['string', 'null'], 'enum' => ['low', 'okay', 'good', null]],
            ],
            'required' => ['action'],
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
        $data = Validator::make($arguments, [
            'action' => ['required', Rule::in(['another', 'battery'])],
            'battery' => ['nullable', Rule::enum(Battery::class)],
        ])->validate();

        $suggestions = new Suggestions;
        $result = $data['action'] === 'another'
            ? $suggestions->another($user)
            : $suggestions->setBattery($user, isset($data['battery']) ? Battery::from($data['battery']) : null);

        return PlanningPayload::suggestion($result);
    }
}
