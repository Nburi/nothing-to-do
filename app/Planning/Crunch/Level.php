<?php

namespace App\Planning\Crunch;

/**
 * How bad a crunch is. Hard deadlines come first (risk F7): only a hard
 * crunch raises a warning; a soft-only one is shown quietly on the board and
 * never pushed, and soft items are never shown in red (risk F5).
 */
enum Level: string
{
    case Soft = 'soft';
    case Hard = 'hard';
}
