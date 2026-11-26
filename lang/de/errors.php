<?php

// Step 5.1: the error pages (404, 4xx, 5xx, maintenance). Said by the app, so short and without blame.
return [
    'back_to_board' => 'Zurück zum Board',
    'back_to_start' => 'Zurück zur Startseite',
    'reload' => 'Seite neu laden',
    'not_found' => [
        'title' => 'Seite nicht gefunden',
        'heading' => 'Diese Seite gibt es nicht.',
        'message' => 'Der Link ist entweder falsch, oder das, wonach du suchst, wurde inzwischen entfernt.',
    ],
    'forbidden' => [
        'title' => 'Kein Zugriff',
        'heading' => 'Dafür fehlt dir der Zugriff.',
        'message' => 'Entweder bist du nicht (mehr) angemeldet, oder diese Seite ist nicht für deinen Account gedacht.',
    ],
    'expired' => [
        'title' => 'Sitzung abgelaufen',
        'heading' => 'Deine Sitzung ist abgelaufen.',
        'message' => 'Das passiert, wenn eine Seite sehr lange offen war. Bitte lade die Seite neu und versuch es erneut.',
    ],
    'too_many' => [
        'title' => 'Zu viele Anfragen',
        'heading' => 'Kurz durchatmen.',
        'message' => 'Da kam gerade zu viel auf einmal. Versuch es in ein paar Sekunden nochmal.',
    ],
    'failed' => [
        'title' => 'Das hat nicht geklappt',
        'heading' => 'Das hat nicht geklappt.',
        'message' => 'Diese Anfrage konnte nicht bearbeitet werden.',
    ],
    'server' => [
        'title' => 'Etwas ist schiefgelaufen',
        'heading' => 'Etwas ist schiefgelaufen.',
        'message' => 'Das liegt nicht an dir — versuch es in ein paar Minuten nochmal. Sollte es öfter passieren, sag uns kurz Bescheid.',
    ],
    'maintenance' => [
        'title' => 'Wartungsarbeiten',
        'heading' => 'Wir sind gleich wieder da.',
        'message' => 'nothing-to-do wird gerade kurz gewartet. Das dauert normalerweise nur wenige Minuten — versuch es gleich nochmal.',
    ],
];
