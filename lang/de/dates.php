<?php

// Step 5.1: how dates are written (App\Support\Dates). PHP date() patterns, and isoFormat patterns under "iso".
return [
    'short' => 'j.n.',
    'padded' => 'd.m.',
    'full' => 'j.n.Y',
    'padded_full' => 'd.m.Y',
    'iso' => [
        'day_long' => 'dddd, D. MMMM',
        'day' => 'D.',
        'day_month' => 'D. MMMM',
        'weekday_date' => 'dd D.M.',
    ],
];
