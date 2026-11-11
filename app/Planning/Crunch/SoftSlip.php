<?php

namespace App\Planning\Crunch;

use Carbon\CarbonImmutable;

/**
 * The soft-slip rule (G2; risk F5 "soft deadlines pile up and create guilt").
 * Pure. A soft deadline is "finished by", set by the user for themself.
 *
 * - First miss: the soft deadline rolls over once, ROLL_OVER_DAYS after the
 *   missed date (at least tomorrow). The counter goes to 1, so the board can
 *   ask "still want this?".
 * - Second miss: the task goes back to Later (no urgency there). A task that
 *   also has a hard deadline never leaves the work lists on its own: only its
 *   soft date is dropped, the hard deadline keeps it in the plan.
 *
 * Nothing is deleted: a task sent to Later keeps its soft date as a record.
 */
final class SoftSlip
{
    public const ROLL_OVER_DAYS = 7;

    public static function decide(?string $softDeadline, int $slips, bool $hasHardDeadline, string $today): SlipDecision
    {
        if ($softDeadline === null || $softDeadline >= $today) {
            return new SlipDecision(SlipAction::None);
        }

        if ($slips === 0) {
            $rolled = CarbonImmutable::parse($softDeadline)->addDays(self::ROLL_OVER_DAYS)->toDateString();
            $tomorrow = CarbonImmutable::parse($today)->addDay()->toDateString();

            return new SlipDecision(SlipAction::RollOver, max($rolled, $tomorrow));
        }

        return new SlipDecision($hasHardDeadline ? SlipAction::DropSoftDate : SlipAction::ToLater);
    }
}
