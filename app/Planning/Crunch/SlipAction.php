<?php

namespace App\Planning\Crunch;

enum SlipAction: string
{
    case None = 'none';
    case RollOver = 'roll_over';
    case ToLater = 'to_later';
    case DropSoftDate = 'drop_soft_date';
}
