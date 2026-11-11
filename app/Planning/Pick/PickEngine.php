<?php

namespace App\Planning\Pick;

use App\Planning\Battery;
use App\Planning\Category;
use App\Planning\Rhythm;
use App\Planning\StartBy;

/**
 * Which task to suggest (refactor step 1.3; R1, R5, R10; risks F3, F4).
 * Rule-based on purpose, so every pick can say why. Pure: no database, no clock.
 *
 * 1. Area filter. Inside a block only tasks whose area the block's category
 *    accepts are candidates (E5: no area fits anywhere; a category without
 *    links accepts only tasks without area). No category = no filter.
 * 2. Tiers, from the start-by dates (DeadlineFeasibility::startBy):
 *    0 = late or has to start today, 1 = has a deadline, 2 = no deadline.
 *    A lower tier always comes first; deadlines are never traded for comfort.
 * 3. The battery orders inside the tiers:
 *    - Okay: deadline order (earliest binding deadline, hard before soft,
 *      smaller first), then the board order.
 *    - Low: a warm-up of up to two Quick tasks goes first, whatever their
 *      tier; then smaller before bigger, and Long / Block work is shrunk to
 *      one session tonight ("the rest comes later"). Late work is never shrunk.
 *    - Good: bigger before smaller, so the largest deadline work lands on the
 *      evening with the most energy.
 * 4. Ties end on the board position and the id, so the order never flickers.
 */
final readonly class PickEngine
{
    public const WARM_UP_COUNT = 2;

    public function __construct(private Rhythm $rhythm = new Rhythm) {}

    /**
     * @param  list<PickCandidate>  $candidates
     * @param  list<int>  $exclude  ids that must not be suggested (the running task, skipped picks)
     * @return list<Pick> best first
     */
    public function rank(array $candidates, Battery $battery, ?Category $category, StartBy $startBy, string $today, array $exclude = []): array
    {
        $pool = array_values(array_filter(
            $candidates,
            fn (PickCandidate $c) => ! in_array($c->id(), $exclude, true)
                && $c->task->remainingMinutes() > 0
                && ($category === null || $category->accepts($c->task->areaId)),
        ));

        $tier = fn (PickCandidate $c): int => match (true) {
            $startBy->isLate($c->id()) => 0,
            $c->task->hasDeadline() && ($startBy->of($c->id()) ?? '9999-12-31') <= $today => 0,
            $c->task->hasDeadline() => 1,
            default => 2,
        };

        $deadlineKey = fn (PickCandidate $c): array => [
            $c->task->bindingDeadline() ?? '9999-12-31',
            $c->task->bindingIsHard() ? 0 : 1,
        ];

        $size = fn (PickCandidate $c): int => $c->task->remainingMinutes();

        $warmUp = [];
        if ($battery === Battery::Low) {
            $quick = array_values(array_filter($pool, fn (PickCandidate $c) => $c->isQuick()));
            usort($quick, fn ($a, $b) => [$tier($a), ...$deadlineKey($a), $a->position, $a->id()]
                <=> [$tier($b), ...$deadlineKey($b), $b->position, $b->id()]);
            $warmUp = array_slice($quick, 0, self::WARM_UP_COUNT);
            $warmIds = array_map(fn (PickCandidate $c) => $c->id(), $warmUp);
            $pool = array_values(array_filter($pool, fn (PickCandidate $c) => ! in_array($c->id(), $warmIds, true)));
        }

        usort($pool, fn (PickCandidate $a, PickCandidate $b) => match ($battery) {
            Battery::Okay => [$tier($a), ...$deadlineKey($a), $size($a), $a->position, $a->id()]
                <=> [$tier($b), ...$deadlineKey($b), $size($b), $b->position, $b->id()],
            Battery::Low => [$tier($a), $size($a), ...$deadlineKey($a), $a->position, $a->id()]
                <=> [$tier($b), $size($b), ...$deadlineKey($b), $b->position, $b->id()],
            Battery::Good => [$tier($a), -$size($a), ...$deadlineKey($a), $a->position, $a->id()]
                <=> [$tier($b), -$size($b), ...$deadlineKey($b), $b->position, $b->id()],
        });

        $picks = [];
        foreach ($warmUp as $c) {
            $picks[] = $this->pick($c, Reason::WarmUp, $startBy);
        }
        foreach ($pool as $c) {
            $picks[] = $this->decide($c, $tier($c), $battery, $startBy);
        }

        return $picks;
    }

    private function decide(PickCandidate $c, int $tier, Battery $battery, StartBy $startBy): Pick
    {
        if ($startBy->isLate($c->id())) {
            return $this->pick($c, Reason::Late, $startBy);
        }

        if ($battery === Battery::Low && $c->isBig()) {
            return $this->pick($c, Reason::Shrunk, $startBy, shrunk: true);
        }

        $reason = match (true) {
            $tier === 0 => Reason::StartsToday,
            $battery === Battery::Good && $c->isBig() && $tier === 1 => Reason::BiggestFirst,
            $tier === 1 => Reason::NextDeadline,
            $c->isQuick() => Reason::QuickOne,
            default => Reason::NoDeadline,
        };

        return $this->pick($c, $reason, $startBy);
    }

    private function pick(PickCandidate $c, Reason $reason, StartBy $startBy, bool $shrunk = false): Pick
    {
        $remaining = $c->task->remainingMinutes();

        return new Pick(
            taskId: $c->id(),
            reason: $reason,
            minutes: $shrunk ? min($remaining, $this->rhythm->workMinutes) : $remaining,
            shrunk: $shrunk && $remaining > $this->rhythm->workMinutes,
            deadline: $c->task->bindingDeadline(),
            deadlineIsHard: $c->task->bindingIsHard(),
            startBy: $startBy->of($c->id()),
        );
    }
}
