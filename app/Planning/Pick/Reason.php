<?php

namespace App\Planning\Pick;

/**
 * Why a task was picked (risk F3: every pick explains itself). The words live
 * in lang/{de,en}/pick.php under `why.<value>`; the engine only decides.
 */
enum Reason: string
{
    /** A deadline can no longer be fully met: start now, it is the closest call. */
    case Late = 'late';

    /** Planned as late as possible, the work still has to begin today. */
    case StartsToday = 'starts_today';

    /** Low battery: a quick one to get going. */
    case WarmUp = 'warm_up';

    /** Low battery: a big task cut down to one session tonight. */
    case Shrunk = 'shrunk';

    /** Good battery: the biggest piece of deadline work while the energy is there. */
    case BiggestFirst = 'biggest_first';

    /** Has a deadline that is not pressing yet; it is simply next in line. */
    case NextDeadline = 'next_deadline';

    /** No deadline, small: done in a few minutes. */
    case QuickOne = 'quick_one';

    /** No deadline: next on the user's own list. */
    case NoDeadline = 'no_deadline';
}
