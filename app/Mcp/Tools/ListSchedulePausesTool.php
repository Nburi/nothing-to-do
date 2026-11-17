<?php

namespace App\Mcp\Tools;

use App\Mcp\McpTool;
use App\Models\SchedulePause;
use App\Models\User;

class ListSchedulePausesTool extends McpTool
{
    public function name(): string
    {
        return 'list_schedule_pauses';
    }

    public function description(): string
    {
        return 'The dates (holidays, sick days, …) on which the weekly plan is switched off, collapsed into ranges. '
            .'Upcoming and current pauses only. Entries placed by hand on a paused day are not affected by a pause.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) []];
    }

    public function requiredModule(): ?string
    {
        return 'weekplan';
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $pauses = $user->schedulePauses()
            ->whereDate('date', '>=', $user->localToday()->toDateString())
            ->ordered()
            ->get();

        return [
            'pauses' => collect(SchedulePause::collapseToRanges($pauses))->map(fn (array $range) => [
                'from' => $range['start']->toDateString(),
                'to' => $range['end']->toDateString(),
                'note' => $range['note'],
            ])->values()->all(),
        ];
    }
}
