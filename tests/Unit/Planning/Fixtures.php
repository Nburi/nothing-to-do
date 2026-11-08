<?php

namespace Tests\Unit\Planning;

use App\Planning\Category;
use App\Planning\Template;
use App\Planning\WeekPlan;

/**
 * Shared fixture week for the planning engine spike (refactor step 0.2).
 *
 * Niels-like week in Europe/Zurich:
 * - School (not pomodoro) Mon–Fri 08:00–16:00
 * - Training (not pomodoro) Tue + Thu 18:00–19:30
 * - Deep work (pomodoro, areas School + Own projects) Mon–Fri 19:30–21:30
 * - Weekend focus (pomodoro, area Own projects) Sat 10:00–12:00
 */
final class Fixtures
{
    public const TZ = 'Europe/Zurich';

    public const SCHOOL = 1;

    public const TRAINING = 2;

    public const DEEP_WORK = 3;

    public const WEEKEND = 4;

    public const AREA_SCHOOL = 10;

    public const AREA_SPORT = 11;

    public const AREA_OWN = 12;

    /** @return array<int, Category> */
    public static function categories(): array
    {
        return [
            self::SCHOOL => new Category(self::SCHOOL, pomodoro: false),
            self::TRAINING => new Category(self::TRAINING, pomodoro: false),
            self::DEEP_WORK => new Category(self::DEEP_WORK, pomodoro: true, areaIds: [self::AREA_SCHOOL, self::AREA_OWN]),
            self::WEEKEND => new Category(self::WEEKEND, pomodoro: true, areaIds: [self::AREA_OWN]),
        ];
    }

    public static function week(): WeekPlan
    {
        return new WeekPlan(self::TZ, [
            new Template(101, [1, 2, 3, 4, 5], '08:00', 480, self::SCHOOL),
            new Template(102, [2, 4], '18:00', 90, self::TRAINING),
            new Template(103, [1, 2, 3, 4, 5], '19:30', 120, self::DEEP_WORK),
            new Template(104, [6], '10:00', 120, self::WEEKEND),
        ]);
    }
}
