<?php

namespace App\Planning\Session;

enum Phase: string
{
    /** No session: the board is shown. */
    case Idle = 'idle';

    /** 25 minutes of work on one task. */
    case Work = 'work';

    case ShortBreak = 'short_break';

    case LongBreak = 'long_break';

    /** The break is over; the next session waits for a tap, with the carried-over task. */
    case Ready = 'ready';

    public function isTimed(): bool
    {
        return in_array($this, [self::Work, self::ShortBreak, self::LongBreak], true);
    }

    public function isBreak(): bool
    {
        return $this === self::ShortBreak || $this === self::LongBreak;
    }
}
