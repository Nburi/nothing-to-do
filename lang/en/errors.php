<?php

// Step 5.1: the error pages (404, 4xx, 5xx, maintenance). Said by the app, so short and without blame.
return [
    'back_to_board' => 'Back to the board',
    'back_to_start' => 'Back to the start page',
    'reload' => 'Reload page',
    'not_found' => [
        'title' => 'Page not found',
        'heading' => 'This page does not exist.',
        'message' => 'Either the link is wrong, or what you are looking for has been removed in the meantime.',
    ],
    'forbidden' => [
        'title' => 'No access',
        'heading' => 'You do not have access to this.',
        'message' => 'Either you are not signed in (any more), or this page is not meant for your account.',
    ],
    'expired' => [
        'title' => 'Session expired',
        'heading' => 'Your session has expired.',
        'message' => 'That happens when a page was open for a very long time. Please reload the page and try again.',
    ],
    'too_many' => [
        'title' => 'Too many requests',
        'heading' => 'Take a breath.',
        'message' => 'That was a bit much at once. Try again in a few seconds.',
    ],
    'failed' => [
        'title' => 'That did not work',
        'heading' => 'That did not work.',
        'message' => 'This request could not be processed.',
    ],
    'server' => [
        'title' => 'Something went wrong',
        'heading' => 'Something went wrong.',
        'message' => 'That is not on you. Try again in a few minutes. If it keeps happening, let us know.',
    ],
    'maintenance' => [
        'title' => 'Maintenance',
        'heading' => 'We will be right back.',
        'message' => 'nothing-to-do is being serviced for a moment. That usually takes only a few minutes. Try again shortly.',
    ],
];
