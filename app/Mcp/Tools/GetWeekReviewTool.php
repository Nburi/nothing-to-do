<?php

namespace App\Mcp\Tools;

use App\Mcp\McpTool;
use App\Models\User;
use App\Services\WeekReview;
use Illuminate\Support\Facades\Validator;

class GetWeekReviewTool extends McpTool
{
    public function name(): string
    {
        return 'get_week_review';
    }

    public function description(): string
    {
        return 'The Wochenrückblick: how a Monday–Sunday week went — completed tasks per day, total, best day, a '
            .'verdict compared with the weeks before, the titles that were completed and what is still open. '
            .'weeks_ago 0 = this week, 1 = last week, …';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => ['weeks_ago' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 104, 'description' => 'Default 0 (this week).']],
        ];
    }

    public function requiredModule(): ?string
    {
        return 'progress';
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $data = Validator::make($arguments, ['weeks_ago' => ['sometimes', 'integer', 'min:0', 'max:104']])->validate();

        $review = WeekReview::for($user, WeekReview::weekStart($user, -($data['weeks_ago'] ?? 0)));

        $review['start'] = $review['start']->toDateString();
        $review['end'] = $review['end']->toDateString();

        return $review;
    }
}
