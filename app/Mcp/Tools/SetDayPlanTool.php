<?php

namespace App\Mcp\Tools;

use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\PlannerToolDefaults;
use App\Models\User;
use App\Services\DayPlanner;
use Illuminate\Validation\ValidationException;

class SetDayPlanTool extends McpTool
{
    use PlannerToolDefaults;

    private const MAX_ITEMS = 40;

    public function name(): string
    {
        return 'set_day_plan';
    }

    public function description(): string
    {
        return 'Set the order of the tasks planned for one day: each listed task (or open homework) is placed on '
            .'that day in the given order, moving it there if it was planned elsewhere. Tasks already on that day '
            .'but NOT listed keep their place (use unplan_task to release one). Max '.self::MAX_ITEMS.' items.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'date' => ['type' => 'string', 'format' => 'date'],
                'items' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'maxItems' => self::MAX_ITEMS,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'task_id' => ['type' => 'integer'],
                            'agenda_entry_id' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
            'required' => ['date', 'items'],
        ];
    }

    public function requiredAbility(): ?string
    {
        return McpAbility::WRITE;
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $date = self::horizonDate($user, $arguments['date'] ?? null);
        $items = $arguments['items'] ?? null;

        if (! is_array($items) || $items === [] || count($items) > self::MAX_ITEMS) {
            throw ValidationException::withMessages(['items' => 'Pass between 1 and '.self::MAX_ITEMS.' items.']);
        }

        $tokens = array_map(fn ($ref) => self::plannerToken($user, is_array($ref) ? $ref : []), array_values($items));

        DayPlanner::assignDay($user, $date, $tokens);

        return ['planned' => count(array_unique($tokens)), 'date' => $date, 'order' => array_values(array_unique($tokens))];
    }
}
