<?php

// Refactor step 5.1: language setting, first-run tour. Short, plain, a little dry.
return [
    'language' => [
        'title' => 'Language',
        'intro' => 'Which language should the app talk to you in? Dates and numbers follow it.',
        'names' => [
            'de' => 'Deutsch',
            'en' => 'English',
        ],
        'applying' => 'Switching …',
        'unknown' => 'That language does not exist. Pick Deutsch or English.',
    ],

    'tour' => [
        'progress' => ':current of :total',
        'skip' => 'Skip',
        'back' => 'Back',
        'next' => 'Next',
        'done' => 'Let\'s go',
        'example' => 'Call the dentist',
        'step1' => [
            'title' => 'Everything lands in the Inbox first',
            'body' => 'One thought, one line: press C or tap the plus, write it down, Enter. That is all you need to do in the moment. Sorting comes later.',
        ],
        'step2' => [
            'title' => 'Once a day you sort',
            'body' => 'About two minutes, one card at a time: To-do, Task or Later. Everything else can wait until it is its turn.',
        ],
        'step3' => [
            'title' => 'In the evening the board folds away',
            'body' => 'The board suggests one thing. You tap Start, the board folds up, and what stays is the card, the first step and the timer.',
        ],
    ],

    'tour_card' => [
        'title' => 'Tour',
        'intro' => 'Three steps on how the app is meant to work: capture, sort, get going in the evening.',
        'open' => 'Show the tour',
    ],
];
