<?php

namespace App\Planning;

/**
 * Load of a day = the remaining work of the tasks planned for it; capacity =
 * its sessions in pomodoro blocks. Load above capacity flags the day. The
 * early warning with hysteresis builds on this in step 1.4.
 */
final readonly class LoadForecast
{
    public function __construct(private Rhythm $rhythm = new Rhythm) {}

    /**
     * @param  list<SessionSlot>  $slots
     * @param  list<PlannedTask>  $plannedTasks
     */
    public function day(string $date, array $slots, array $plannedTasks): DayLoad
    {
        $capacityMinutes = count($slots) * $this->rhythm->workMinutes;
        $load = array_sum(array_map(fn (PlannedTask $t) => $t->remainingMinutes(), $plannedTasks));

        return new DayLoad($date, count($slots), $capacityMinutes, $load, $load > $capacityMinutes);
    }
}
