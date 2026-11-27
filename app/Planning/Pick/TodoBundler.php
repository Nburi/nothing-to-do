<?php

namespace App\Planning\Pick;

use App\Planning\Rhythm;

/**
 * The To-do session (2026-11-24): the small things of a day, bundled into one 25 minute session.
 * Pure: it only fills a session from picks that are already in priority order.
 *
 * The most urgent To-do always goes in (even a big one, which then fills the session alone);
 * after it the next To-dos that still fit are added in priority order, and a To-do that does
 * not fit is skipped, not waited for. What did not fit waits for the next session or day.
 */
final readonly class TodoBundler
{
    public function __construct(private Rhythm $rhythm = new Rhythm) {}

    /**
     * @param  list<Pick>  $picks  To-do picks, most urgent first
     * @return list<Pick> the picks of one session, in the order they are worked
     */
    public function bundle(array $picks): array
    {
        $session = [];
        $used = 0;

        foreach ($picks as $pick) {
            if ($used >= $this->rhythm->workMinutes) {
                break;
            }
            if ($session !== [] && $used + $pick->minutes > $this->rhythm->workMinutes) {
                continue;
            }

            $session[] = $pick;
            $used += $pick->minutes;
        }

        return $session;
    }

    /**
     * How many 25 minute slots a bundle takes: one, or more for a single task that is longer than a slot.
     *
     * @param  list<Pick>  $bundle
     */
    public function slotsOf(array $bundle): int
    {
        return max(1, (int) ceil(array_sum(array_map(fn (Pick $p) => $p->minutes, $bundle)) / $this->rhythm->workMinutes));
    }
}
