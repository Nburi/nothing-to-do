<?php

namespace App\Planning;

/**
 * Where tonight's reset stands for the board (step 3.2, R2, G4).
 *
 * Hidden: show the board. Offer: the calm screen with 10:00 and "Los". Running: the
 * countdown. Battery: the one question at the end of the reset.
 */
enum ResetStage: string
{
    case Hidden = 'hidden';
    case Offer = 'offer';
    case Running = 'running';
    case Battery = 'battery';

    public function covers(): bool
    {
        return $this !== self::Hidden;
    }
}
