<?php

namespace App\Planning;

/**
 * The explicit choice every change to a repeating block needs (R4,
 * CONVENTIONS 8): one occurrence, or the series. There is no default on
 * purpose; the caller (the editor in 4.1) always asks.
 */
enum ChangeScope: string
{
    /** Only this date. The template stays as it is. */
    case ThisWeekOnly = 'this_week_only';

    /** The template, from tomorrow on. Today and the past stay as they were. */
    case EveryWeek = 'every_week';
}
