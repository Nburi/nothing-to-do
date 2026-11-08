<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * The user's local time for the planning engines. Users store a base UTC
 * offset plus an "auto DST" switch that follows the EU rule (User::utcOffsetMinutes).
 *
 * For the offsets where that is exactly a real zone (0, +1, +2 with auto DST:
 * London, Zurich, Helsinki switch at the same UTC instant) the IANA zone is
 * used, so dates in the coming weeks get their own correct offset, DST change
 * included (risk C2). Every other setting gets the fixed offset in force at
 * `$at`; across a DST change such a user's blocks can be an hour off, which
 * is the same limit the old app has.
 */
final class UserClock
{
    private const EU_ZONES = [0 => 'Europe/London', 60 => 'Europe/Zurich', 120 => 'Europe/Helsinki'];

    public static function timezone(User $user, ?CarbonImmutable $at = null): string
    {
        $base = (int) round($user->timezoneOffsetHours() * 60);

        if ($user->timezone_auto_dst && isset(self::EU_ZONES[$base])) {
            return self::EU_ZONES[$base];
        }

        $minutes = $user->utcOffsetMinutes(Carbon::instance($at ?? CarbonImmutable::now()));

        return sprintf('%s%02d:%02d', $minutes < 0 ? '-' : '+', intdiv(abs($minutes), 60), abs($minutes) % 60);
    }

    /** "Now" in the user's local time. */
    public static function now(User $user, ?CarbonImmutable $now = null): CarbonImmutable
    {
        $now ??= CarbonImmutable::now();

        return $now->setTimezone(self::timezone($user, $now));
    }
}
