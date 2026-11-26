<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Dates as the user's language writes them (step 5.1). German: 25.11. and Mittwoch, 25. November;
 * English: 25 Nov and Wednesday, 25 November (day first, month as a word, so no one has to guess
 * between 3/4 and 4/3). The patterns live in lang/{de,en}/dates.php.
 */
final class Dates
{
    /** 25.11. / 25 Nov */
    public static function short(CarbonInterface|string $date): string
    {
        return self::php($date, 'short');
    }

    /** 25.11. / 25 Nov, always two digits in German (stage dates on a card) */
    public static function padded(CarbonInterface|string $date): string
    {
        return self::php($date, 'padded');
    }

    /** 25.11.2026 / 25 Nov 2026 */
    public static function full(CarbonInterface|string $date): string
    {
        return self::php($date, 'full');
    }

    /** 25.11.2026 / 25 Nov 2026, two digits in German */
    public static function paddedFull(CarbonInterface|string $date): string
    {
        return self::php($date, 'padded_full');
    }

    /** A named isoFormat pattern from the dates lang file ("iso.day_long": Mittwoch, 25. November). */
    public static function iso(CarbonInterface|string $date, string $name): string
    {
        return self::carbon($date)->isoFormat(__('dates.iso.'.$name));
    }

    private static function php(CarbonInterface|string $date, string $name): string
    {
        return self::carbon($date)->translatedFormat(__('dates.'.$name));
    }

    private static function carbon(CarbonInterface|string $date): CarbonInterface
    {
        return Carbon::parse($date)->locale(app()->getLocale());
    }
}
