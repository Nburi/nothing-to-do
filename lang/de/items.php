<?php

// Refactor step 4.9: editing, moving and deleting single items on the board and on the Ideen cards.
return [
    'menu' => [
        'open' => 'Mehr zu «:title»',
        'title' => 'Aktionen',
        'edit' => 'Bearbeiten',
        'move' => 'Verschieben nach',
        'move_to' => 'Nach :list',
        'delete' => 'Löschen',
    ],
    'sheet' => [
        'title' => 'Bearbeiten',
        'close' => 'Schliessen',
        'title_label' => 'Titel',
        'list' => 'Liste',
        'size' => 'Grösse',
        'size_hint' => 'Nochmal tippen nimmt die Angabe weg.',
        'notes' => 'Notizen',
        'notes_placeholder' => 'Alles, was nicht in den Titel passt.',
        'save' => 'Speichern',
        'saving' => 'Speichert …',
        'cancel' => 'Abbrechen',
        'delete' => 'Löschen',
        'esc' => 'Esc schliesst',
        'later_no_hard' => 'Bei Später gibt es keinen harten Termin.',
    ],
    'toast' => [
        'saved' => '«:title» gespeichert.',
        'moved' => '«:title» ist jetzt bei :list.',
        'deleted' => '«:title» gelöscht.',
        'undo' => 'Rückgängig',
        'edit_undone' => 'Änderung zurückgenommen.',
        'delete_undone' => '«:title» ist wieder da.',
        'dismiss' => 'Schliessen',
    ],
    'errors' => [
        'title' => 'Gib dem Eintrag einen Titel.',
        'title_max' => 'Höchstens :max Zeichen. Der Rest passt in die Notizen.',
        'notes' => 'Höchstens :max Zeichen in den Notizen.',
        'list' => 'Wähle Inbox, To-dos, Tasks oder Später.',
        'locked' => 'Das läuft gerade in deiner Session. Beende die Session zuerst.',
        'gone' => 'Den Eintrag gibt es nicht mehr. Die Liste ist aktualisiert.',
        'failed' => 'Das hat nicht geklappt. Versuch es gleich nochmal.',
    ],
];
