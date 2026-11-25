<?php

// Step 5.1: how dates are written (App\Support\Dates). PHP date() patterns, and isoFormat patterns under "iso".
return [
    'short' => 'j M',
    'padded' => 'j M',
    'full' => 'j M Y',
    'padded_full' => 'j M Y',
    'iso' => [
        'day_long' => 'dddd, D MMMM',
        'day' => 'D',
        'day_month' => 'D MMMM',
        'weekday_date' => 'dd D MMM',
    ],
];
