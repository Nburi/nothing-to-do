<?php

// Refactor step 5.1: language setting, first-run tour. Short, plain, a little dry; informal "du".
return [
    'language' => [
        'title' => 'Sprache',
        'intro' => 'In welcher Sprache soll die App mit dir reden? Datum und Zahlen richten sich danach.',
        'names' => [
            'de' => 'Deutsch',
            'en' => 'English',
        ],
        'applying' => 'Wird umgestellt …',
        'unknown' => 'Diese Sprache gibt es nicht. Wähle Deutsch oder English.',
    ],

    'tour' => [
        'progress' => ':current von :total',
        'skip' => 'Überspringen',
        'back' => 'Zurück',
        'next' => 'Weiter',
        'done' => 'Los geht\'s',
        'example' => 'Zahnarzt anrufen',
        'step1' => [
            'title' => 'Alles landet zuerst in der Inbox',
            'body' => 'Ein Einfall, eine Zeile: Taste C oder das Plus, hinschreiben, Enter. Mehr musst du in dem Moment nicht tun. Einordnen kommt später.',
        ],
        'step2' => [
            'title' => 'Einmal am Tag sortierst du',
            'body' => 'Etwa zwei Minuten, eine Karte nach der anderen: To-do, Task oder Später. Alles andere darf warten, bis es dran ist.',
        ],
        'step3' => [
            'title' => 'Abends faltet sich das Board weg',
            'body' => 'Das Board schlägt dir eine Sache vor. Du tippst auf Start, das Board klappt zusammen, und es bleiben die Karte, der erste Schritt und der Timer.',
        ],
    ],

    'tour_card' => [
        'title' => 'Rundgang',
        'intro' => 'Drei Schritte, wie die App gedacht ist: festhalten, sortieren, abends loslegen.',
        'open' => 'Rundgang ansehen',
    ],
];
