<?php

namespace App\Services\Planning;

use RuntimeException;

/** The item cannot be deleted right now: it is the task of the running session (step 4.9). */
final class ItemLocked extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The task of the running session cannot be deleted.');
    }
}
