<?php

namespace App\Services\Planning;

use Carbon\CarbonImmutable;

/** A date as the planning copy says it: heute / morgen / weekday within a week / "j.n.". */
final class DayLabel
{
    public static function for(string $date, CarbonImmutable $now): string
    {
        $day = CarbonImmutable::parse($date, $now->getTimezone());
        $days = (int) CarbonImmutable::parse($now->toDateString(), $now->getTimezone())->diffInDays($day, false);

        return match (true) {
            $days <= 0 => __('pick.day.today'),
            $days === 1 => __('pick.day.tomorrow'),
            $days <= 6 => $day->locale(app()->getLocale())->isoFormat('dddd'),
            default => $day->format('j.n.'),
        };
    }
}
