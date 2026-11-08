<?php

namespace App\Services\Planning;

enum SuggestionStatus: string
{
    /** Inside a pomodoro block, with a task to suggest. */
    case Pick = 'pick';

    /** Outside every pomodoro block: no work is suggested (REQUIREMENTS § 3.7); `nextBlockAt` says when work time begins. */
    case OutsideBlocks = 'outside_blocks';

    /** Inside a block, but nothing on the To-do and Task lists fits it. */
    case Empty = 'empty';
}
