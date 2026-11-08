<?php

namespace App\Planning\Crunch;

/**
 * The early warning (R5, REQUIREMENTS § 4; risk F7 "cries wolf"). Pure.
 *
 * - **On** as soon as any deadline work does not fit (Forecast::hasCrunch()).
 *   The horizon is at least two weeks, so a crunch shows up as early as the
 *   plan can see it, normally well over two days ahead.
 * - **Off** only when nothing is late *and* the tightest deadline has at
 *   least one session of room (CLEAR_SLACK_MINUTES). In between the state
 *   is kept: that gap is the hysteresis that stops it flickering when one
 *   quick task comes and goes.
 * - **Notify** only for hard deadlines (soft-only crunches are shown quietly),
 *   and at most once per local day, even if it switches off and on again.
 *   A soft crunch that turns hard is a new reason to notify (same daily limit).
 */
final readonly class EarlyWarning
{
    public const CLEAR_SLACK_MINUTES = 25;

    public function evaluate(Forecast $forecast, AlertState $previous, string $today): AlertDecision
    {
        $level = $forecast->level();

        if ($level === null) {
            $roomy = $forecast->slackMinutes === null || $forecast->slackMinutes >= self::CLEAR_SLACK_MINUTES;
            if (! $previous->active) {
                return new AlertDecision($previous, notify: false, cleared: false);
            }
            if (! $roomy) {
                return new AlertDecision(new AlertState(true, $previous->level, $previous->crunchDate, 0, $previous->raisedOn), notify: false, cleared: false);
            }

            return new AlertDecision(new AlertState(raisedOn: $previous->raisedOn), notify: false, cleared: true);
        }

        $escalated = $level === Level::Hard && (! $previous->active || $previous->level !== Level::Hard);
        $notify = $escalated && $previous->raisedOn !== $today;

        return new AlertDecision(
            new AlertState(
                true,
                $level,
                $forecast->crunchDate(),
                $forecast->missingMinutes($level),
                $notify ? $today : $previous->raisedOn,
            ),
            $notify,
            cleared: false,
        );
    }
}
