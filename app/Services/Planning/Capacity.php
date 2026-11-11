<?php

namespace App\Services\Planning;

use App\Models\User;
use App\Planning\Crunch\Forecast;
use App\Planning\Crunch\LoadPlanner;
use Carbon\CarbonImmutable;

/**
 * Refactor step 1.4: capacity and load forecast per day for a user (R5, R10).
 * Read-only; see LoadPlanner for the rules.
 */
final class Capacity
{
    public function __construct(private readonly WeekPlanReader $reader = new WeekPlanReader) {}

    public function forecast(User $user, ?CarbonImmutable $now = null): Forecast
    {
        return $this->forecastFor(PlanningContext::for($user, $now, $this->reader));
    }

    public function forecastFor(PlanningContext $context): Forecast
    {
        return (new LoadPlanner($context->categories))->forecast($context->plannedTasks(), $context->slots, $context->now);
    }
}
