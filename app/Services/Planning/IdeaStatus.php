<?php

namespace App\Services\Planning;

/** What the idle board shows for free time (step 3.4). */
enum IdeaStatus: string
{
    /** Not free time now (a block runs, the night, too little time left) or nothing in Later. */
    case Hidden = 'hidden';

    /** A session (work or break) runs: never an idea then (R6, CONVENTIONS 12). */
    case Session = 'session';

    /** Free time, deadlines covered: one idea from Later. */
    case Offer = 'offer';

    /** The user kept this free slot "mine": no idea, "Der Rest gehört dir." */
    case Mine = 'mine';

    /** Every idea in Later was already offered today (F9: none twice the same day). */
    case Exhausted = 'exhausted';

    /** Free time, but a deadline does not fit into the pomodoro blocks: no idea, say why. */
    case Deadlines = 'deadlines';

    /** Statuses that replace the "no work block" card on the board. */
    public function showsCard(): bool
    {
        return in_array($this, [self::Offer, self::Mine, self::Exhausted], true);
    }
}
