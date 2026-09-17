<?php

// Edit the Tagesüberblick's greeting lines here — no code changes needed.
// See App\Livewire\DayPreview::buildGreeting() for how these are used.

return [

    /*
    |--------------------------------------------------------------------------
    | Greeting pools
    |--------------------------------------------------------------------------
    |
    | One line is picked at random each time the Tagesüberblick page opens,
    | from whichever pool matches the user's own local hour right now
    | (morning: before 12, midday: 12–17, evening: from 18 on). ":name" is
    | replaced with the user's own name. Add, remove, or reword lines freely —
    | each pool just needs at least one entry.
    |
    */

    'greetings' => [
        'morning' => [
            "Guten Morgen, :name.",
            "Auf geht's, :name.",
            'Morgen. Kaffee zuerst?',
            'Ein neuer Tag, :name.',
            'Bereit für Grosses?',
            'Ein wundervoller Tag liegt vor dir, :name.',
            'Hey :name — dein Tag im Überblick.',
            'Ready für den Tag, :name?',
            ':name, was läuft heute?',
        ],

        'midday' => [
            'Hey :name — dein Tag im Überblick.',
            'Zweite Tageshälfte, gleiche Prioritäten.',
            "Auf geht's, :name.",
        ],

        'evening' => [
            'Guten Abend, :name.',
            'Der Tag ist fast rum — hier steht noch was aus.',
            'Später Einstieg heute, aber besser als nicht.',
            'Wo warst du so lange, :name?',
            'Eingestempelt für die Abendschicht.'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Streak milestones
    |--------------------------------------------------------------------------
    |
    | On a day whose exact current streak matches one of these keys, this
    | message always wins over the pool above for that one visit — no ":name"
    | placeholder here since these read fine without it. Add a new key (e.g.
    | 200) to add a new milestone; remove a key to drop it.
    |
    */

    'milestones' => [
        7 => 'Tag 7 deiner Serie — eine Woche am Stück.',
        14 => 'Tag 14 deiner Serie — zwei Wochen ohne Lücke.',
        30 => 'Tag 30 deiner Serie. Das ist kein Zufall mehr.',
        50 => 'Tag 50 deiner Serie.',
        100 => 'Tag 100 deiner Serie.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Morning notification
    |--------------------------------------------------------------------------
    |
    | A once-a-day push ("Dein Tag ist bereit") for anyone who turned it on in
    | Settings → Benachrichtigungen. "time" is HH:MM in the user's own local
    | time — the push fires once that time has passed, same "due time, not an
    | exact-minute match" shape every other reminder in this app already uses,
    | so a delayed cron tick still fires on the next run instead of losing it.
    | "title" is edited here freely; the message body is never a static line —
    | it's always a live summary built from that day's real data (see
    | App\Services\DayPreviewData::notificationSummary()), since "how many
    | tasks/terms today" is the entire point of the nudge.
    |
    */

    'notification' => [
        'time' => '07:30',
        'title' => 'Dein Tag ist bereit',
    ],

];
