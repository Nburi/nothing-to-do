<?php

namespace App\Planning;

use Carbon\CarbonImmutable;

/**
 * Where 25 minute sessions fit into one day's blocks (REQUIREMENTS § 3–4).
 *
 * - Only blocks of categories with the pomodoro switch are work time.
 * - Any other block (a fixed appointment, school, training) inside a pomodoro
 *   block cuts it: fixed appointments win.
 * - Overlapping pomodoro blocks are counted once; the overlap belongs to the
 *   block that started first.
 * - Sessions never cross a gap. After the n-th session of the day comes its
 *   break (5 min, every fourth 15 min); the next session starts after that
 *   break at the earliest, also when it lies in another block of the same day.
 *
 * Capacity of a day = number of slots. Pure: no database, no clock.
 */
final readonly class SlotCalculator
{
    /** @param  array<int, Category>  $categories  keyed by category id */
    public function __construct(
        private array $categories,
        private Rhythm $rhythm = new Rhythm,
    ) {}

    /**
     * @param  list<Block>  $blocks  all blocks of one day
     * @return list<SessionSlot>
     */
    public function slots(array $blocks): array
    {
        $pieces = $this->workPieces($blocks);
        if ($pieces === []) {
            return [];
        }

        $timezone = $blocks[0]->start->getTimezone();
        $work = $this->rhythm->workMinutes * 60;
        $slots = [];
        $earliest = PHP_INT_MIN;

        foreach ($pieces as [$from, $to, $categoryId]) {
            $t = max($from, $earliest);
            while ($t + $work <= $to) {
                $number = count($slots) + 1;
                $slots[] = new SessionSlot(
                    CarbonImmutable::createFromTimestamp($t, $timezone),
                    CarbonImmutable::createFromTimestamp($t + $work, $timezone),
                    $categoryId,
                    $number,
                );
                $earliest = $t + $work + $this->rhythm->breakMinutesAfter($number) * 60;
                $t = $earliest;
            }
        }

        return $slots;
    }

    /** @param  list<Block>  $blocks */
    public function capacity(array $blocks): int
    {
        return count($this->slots($blocks));
    }

    /**
     * Disjoint stretches of work time, sorted by start.
     *
     * @param  list<Block>  $blocks
     * @return list<array{0:int,1:int,2:int}> [from, to, categoryId] as unix timestamps
     */
    private function workPieces(array $blocks): array
    {
        $pomodoro = [];
        $fixed = [];
        foreach ($blocks as $block) {
            $interval = [$block->start->getTimestamp(), $block->end->getTimestamp()];
            if ($interval[1] <= $interval[0]) {
                continue;
            }
            $category = $block->categoryId === null ? null : ($this->categories[$block->categoryId] ?? null);
            if ($category?->pomodoro) {
                $pomodoro[] = [$interval, $category->id];
            } else {
                $fixed[] = $interval;
            }
        }

        usort($pomodoro, fn ($a, $b) => $a[0][0] <=> $b[0][0]);

        $pieces = [];
        $claimed = [];
        foreach ($pomodoro as [$interval, $categoryId]) {
            foreach (self::subtract([$interval], [...$fixed, ...$claimed]) as [$from, $to]) {
                $pieces[] = [$from, $to, $categoryId];
            }
            $claimed[] = $interval;
        }

        usort($pieces, fn ($a, $b) => $a[0] <=> $b[0]);

        return $pieces;
    }

    /**
     * @param  list<array{0:int,1:int}>  $intervals
     * @param  list<array{0:int,1:int}>  $cuts
     * @return list<array{0:int,1:int}>
     */
    private static function subtract(array $intervals, array $cuts): array
    {
        foreach ($cuts as [$cutFrom, $cutTo]) {
            $next = [];
            foreach ($intervals as [$from, $to]) {
                if ($cutTo <= $from || $cutFrom >= $to) {
                    $next[] = [$from, $to];

                    continue;
                }
                if ($cutFrom > $from) {
                    $next[] = [$from, $cutFrom];
                }
                if ($cutTo < $to) {
                    $next[] = [$cutTo, $to];
                }
            }
            $intervals = $next;
        }

        return $intervals;
    }
}
