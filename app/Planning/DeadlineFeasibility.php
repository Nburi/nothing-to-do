<?php

namespace App\Planning;

use Carbon\CarbonImmutable;

/**
 * Can every deadline still be met with the sessions that are left? (R5, R10)
 *
 * Order (no priority field): earliest binding deadline first; on the same date
 * hard before soft; then the smaller remaining work; then the id, so the
 * order never flickers.
 *
 * Pass 1 fills the remaining sessions earliest-deadline-first. Quick tasks
 * share a session (work is counted in minutes). A task only uses sessions of
 * categories linked to its area. If a hard deadline is missed, pass 2 plans
 * hard work as late as possible before its deadline and lets soft work use
 * what is left, so soft deadlines are the ones that slip (R5, G2).
 *
 * Greedy, not an optimal assignment: with area links a cleverer split can
 * exist. Good enough to warn; the user decides in the crunch screen.
 */
final readonly class DeadlineFeasibility
{
    /** @param  array<int, Category>  $categories */
    public function __construct(
        private array $categories,
        private Rhythm $rhythm = new Rhythm,
    ) {}

    /**
     * @param  list<PlannedTask>  $tasks
     * @return list<PlannedTask> dated tasks only, in start-by order
     */
    public static function order(array $tasks): array
    {
        $dated = array_values(array_filter($tasks, fn (PlannedTask $t) => $t->hasDeadline()));
        usort($dated, fn (PlannedTask $a, PlannedTask $b) => [$a->bindingDeadline(), $a->bindingIsHard() ? 0 : 1, $a->remainingMinutes(), $a->id]
            <=> [$b->bindingDeadline(), $b->bindingIsHard() ? 0 : 1, $b->remainingMinutes(), $b->id]);

        return $dated;
    }

    /**
     * @param  list<PlannedTask>  $tasks
     * @param  array<string, list<SessionSlot>>  $slotsByDate  local date => that day's slots
     */
    public function check(array $tasks, array $slotsByDate, CarbonImmutable $now): FeasibilityResult
    {
        $ordered = self::order($tasks);
        $slots = $this->openSlots($slotsByDate, $now);

        $first = $this->fill($ordered, $slots, forward: true);
        $lateFirst = $this->lateIds($ordered, $first);
        $hardLate = array_filter($ordered, fn (PlannedTask $t) => $t->bindingIsHard() && in_array($t->id, $lateFirst, true));

        if ($hardLate === []) {
            return $this->result($ordered, $first, []);
        }

        $hard = array_values(array_filter($ordered, fn (PlannedTask $t) => $t->bindingIsHard()));
        $soft = array_values(array_filter($ordered, fn (PlannedTask $t) => ! $t->bindingIsHard()));

        $second = $this->fill(array_reverse($hard), $slots, forward: false);
        $second = $this->fill($soft, $second['slots'], forward: true, carry: $second);

        $slipped = array_values(array_filter(
            $this->lateIds($soft, $second),
            fn (int $id) => ! in_array($id, $lateFirst, true),
        ));

        return $this->result($ordered, $second, $slipped);
    }

    /**
     * @param  array<string, list<SessionSlot>>  $slotsByDate
     * @return list<array{date:string,category:int,free:int}>
     */
    private function openSlots(array $slotsByDate, CarbonImmutable $now): array
    {
        ksort($slotsByDate);
        $open = [];
        foreach ($slotsByDate as $date => $daySlots) {
            foreach ($daySlots as $slot) {
                if ($slot->start->getTimestamp() >= $now->getTimestamp()) {
                    $open[] = ['date' => $date, 'category' => $slot->categoryId, 'free' => $this->rhythm->workMinutes];
                }
            }
        }

        return $open;
    }

    /**
     * @param  list<PlannedTask>  $tasks  in the order they get to choose
     * @param  list<array{date:string,category:int,free:int}>  $slots
     * @param  array{slots:list<array>,minutes:array<int,array<string,int>>,remaining:array<int,int>,used:array<int,true>}|null  $carry
     * @return array{slots:list<array>,minutes:array<int,array<string,int>>,remaining:array<int,int>,used:array<int,true>}
     */
    private function fill(array $tasks, array $slots, bool $forward, ?array $carry = null): array
    {
        $minutes = $carry['minutes'] ?? [];
        $remaining = $carry['remaining'] ?? [];
        $used = $carry['used'] ?? [];
        $indexes = $forward ? array_keys($slots) : array_reverse(array_keys($slots));

        foreach ($tasks as $task) {
            $left = $task->remainingMinutes();
            $deadline = $task->bindingDeadline();
            $minutes[$task->id] ??= [];

            foreach ($indexes as $i) {
                if ($left === 0) {
                    break;
                }
                $slot = $slots[$i];
                if ($slot['free'] === 0 || $slot['date'] > $deadline) {
                    continue;
                }
                $category = $this->categories[$slot['category']] ?? null;
                if (! $category?->accepts($task->areaId)) {
                    continue;
                }

                $take = min($left, $slot['free']);
                $slots[$i]['free'] -= $take;
                $left -= $take;
                $used[$i] = true;
                $minutes[$task->id][$slot['date']] = ($minutes[$task->id][$slot['date']] ?? 0) + $take;
            }

            ksort($minutes[$task->id]);
            $remaining[$task->id] = $left;
        }

        return ['slots' => $slots, 'minutes' => $minutes, 'remaining' => $remaining, 'used' => $used];
    }

    /** @return list<int> */
    private function lateIds(array $tasks, array $fill): array
    {
        return array_values(array_map(
            fn (PlannedTask $t) => $t->id,
            array_filter($tasks, fn (PlannedTask $t) => ($fill['remaining'][$t->id] ?? 0) > 0),
        ));
    }

    /** @param  list<int>  $slipped */
    private function result(array $ordered, array $fill, array $slipped): FeasibilityResult
    {
        $finishesOn = [];
        $missing = [];
        $firstLate = null;

        foreach ($ordered as $task) {
            $left = $fill['remaining'][$task->id] ?? 0;
            if ($left > 0) {
                $finishesOn[$task->id] = null;
                $missing[$task->id] = $left;
                $firstLate = $firstLate === null ? $task->bindingDeadline() : min($firstLate, $task->bindingDeadline());
            } else {
                $dates = array_keys($fill['minutes'][$task->id] ?? []);
                $finishesOn[$task->id] = $dates === [] ? null : end($dates);
            }
        }

        $sessionsUsed = [];
        foreach (array_keys($fill['used']) as $i) {
            $date = $fill['slots'][$i]['date'];
            $sessionsUsed[$date] = ($sessionsUsed[$date] ?? 0) + 1;
        }

        $late = array_keys($missing);
        sort($late);

        return new FeasibilityResult(
            $finishesOn,
            array_filter($fill['minutes'], fn (array $m) => $m !== []),
            $late,
            $slipped,
            $missing,
            $firstLate,
            $sessionsUsed,
        );
    }
}
