<?php

namespace App\Services\Planning;

use App\Models\CrunchAlert;
use App\Models\User;
use App\Planning\Crunch\AlertDecision;
use App\Planning\Crunch\AlertState;
use App\Planning\Crunch\EarlyWarning;
use App\Support\UserClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Refactor step 1.4: the early warning with its stored state (hysteresis and
 * "at most one warning a day" need memory, see EarlyWarning). check() is
 * the one write path; it runs from the hourly planning command and may run
 * from the board. Sending the warning (push, crunch screen) is step 4.2: the
 * decision's `notify` says when.
 */
final class CrunchWatch
{
    public function __construct(
        private readonly Capacity $capacity = new Capacity,
        private readonly EarlyWarning $warning = new EarlyWarning,
    ) {}

    public function state(User $user): AlertState
    {
        return CrunchAlert::query()->where('user_id', $user->id)->first()?->toState() ?? AlertState::none();
    }

    public function check(User $user, ?CarbonImmutable $now = null): AlertDecision
    {
        $now = UserClock::now($user, $now);
        $forecast = $this->capacity->forecast($user, $now);

        return DB::transaction(function () use ($user, $now, $forecast) {
            $row = CrunchAlert::query()->where('user_id', $user->id)->lockForUpdate()->first();
            $decision = $this->warning->evaluate($forecast, $row?->toState() ?? AlertState::none(), $now->toDateString());
            $state = $decision->state;

            if ($row === null && ! $state->active) {
                return $decision; // nothing to remember yet
            }

            $row ??= new CrunchAlert(['active' => false]);
            $row->user_id = $user->id;
            $row->fill([
                'active' => $state->active,
                'level' => $state->level,
                'crunch_date' => $state->crunchDate,
                'missing_minutes' => $state->missingMinutes,
                'raised_on' => $state->raisedOn,
                'cleared_at' => $decision->cleared ? $now : ($state->active ? null : $row->cleared_at),
            ])->save();

            return $decision;
        });
    }
}
