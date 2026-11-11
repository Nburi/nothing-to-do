<?php

namespace App\Planning\Crunch;

use App\Planning\Category;
use App\Planning\DayLoad;
use App\Planning\DeadlineFeasibility;
use App\Planning\PlannedTask;
use App\Planning\Rhythm;
use App\Planning\SessionSlot;
use Carbon\CarbonImmutable;

/**
 * Builds the load forecast (refactor step 1.4; R5, R10; risk C4). Pure.
 *
 * - Capacity of a day = its 25 minute sessions in pomodoro blocks; today
 *   counts only the sessions that have not started yet.
 * - Load of a day = the deadline work DeadlineFeasibility plans into it
 *   (earliest deadline first, quick tasks share a session, area links
 *   respected, soft work gives way to hard) plus the work that fits nowhere,
 *   put on its deadline day (today when the deadline is already past).
 * - A day is overloaded when work is left over on it, or load > capacity.
 * - Slack = the room at the tightest deadline: the smallest "sessions left
 *   until D minus work due by D" over all deadline dates D, in minutes and
 *   ignoring areas. The early warning uses it to switch off only when there
 *   is real room again (hysteresis).
 *
 * Tasks without a deadline are not load: nothing plans them onto a day.
 */
final readonly class LoadPlanner
{
    /** @param  array<int, Category>  $categories */
    public function __construct(
        private array $categories,
        private Rhythm $rhythm = new Rhythm,
    ) {}

    /**
     * @param  list<PlannedTask>  $tasks
     * @param  array<string, list<SessionSlot>>  $slotsByDate  today … horizon
     * @param  CarbonImmutable  $now  in the user's timezone
     */
    public function forecast(array $tasks, array $slotsByDate, CarbonImmutable $now): Forecast
    {
        ksort($slotsByDate);
        $today = $now->toDateString();
        $plan = (new DeadlineFeasibility($this->categories, $this->rhythm))->check($tasks, $slotsByDate, $now);
        $byId = [];
        foreach ($tasks as $task) {
            $byId[$task->id] = $task;
        }

        $capacity = [];
        foreach ($slotsByDate as $date => $slots) {
            $open = array_filter($slots, fn (SessionSlot $s) => $s->start->getTimestamp() >= $now->getTimestamp());
            $capacity[$date] = count($open);
        }

        $load = array_fill_keys(array_keys($slotsByDate), 0);
        foreach ($plan->minutesByDate as $minutes) {
            foreach ($minutes as $date => $m) {
                $load[$date] = ($load[$date] ?? 0) + $m;
            }
        }

        $overflow = [];
        foreach ($plan->missingMinutes as $id => $missing) {
            $date = max($today, $byId[$id]->bindingDeadline() ?? $today);
            $overflow[$date] = ($overflow[$date] ?? 0) + $missing;
            $load[$date] = ($load[$date] ?? 0) + $missing;
        }
        ksort($load);

        $days = [];
        foreach ($load as $date => $minutes) {
            $sessions = $capacity[$date] ?? 0;
            $capacityMinutes = $sessions * $this->rhythm->workMinutes;
            $days[] = new DayLoad($date, $sessions, $capacityMinutes, $minutes, ($overflow[$date] ?? 0) > 0 || $minutes > $capacityMinutes);
        }

        $hardLate = [];
        $softLate = [];
        foreach ($plan->lateTaskIds as $id) {
            if ($byId[$id]->bindingIsHard()) {
                $hardLate[] = $id;
            } else {
                $softLate[] = $id;
            }
        }

        return new Forecast($days, $plan, $hardLate, $softLate, $this->slack($tasks, $capacity, $today));
    }

    /**
     * @param  list<PlannedTask>  $tasks
     * @param  array<string, int>  $capacity  date => open sessions
     */
    private function slack(array $tasks, array $capacity, string $today): ?int
    {
        $due = [];
        foreach ($tasks as $task) {
            if ($task->hasDeadline() && $task->remainingMinutes() > 0) {
                $date = max($today, $task->bindingDeadline());
                $due[$date] = ($due[$date] ?? 0) + $task->remainingMinutes();
            }
        }
        if ($due === []) {
            return null;
        }
        ksort($due);

        $dates = array_unique([...array_keys($capacity), ...array_keys($due)]);
        sort($dates);

        $slack = null;
        $room = 0;
        $work = 0;
        foreach ($dates as $date) {
            $room += ($capacity[$date] ?? 0) * $this->rhythm->workMinutes;
            if (isset($due[$date])) {
                $work += $due[$date];
                $slack = $slack === null ? $room - $work : min($slack, $room - $work);
            }
        }

        return $slack;
    }
}
