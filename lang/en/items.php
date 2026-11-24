<?php

// Refactor step 4.9: editing, moving and deleting single items on the board and on the Ideen cards.
return [
    'menu' => [
        'open' => 'More for “:title”',
        'title' => 'Actions',
        'edit' => 'Edit',
        'move' => 'Move to',
        'move_to' => 'To :list',
        'delete' => 'Delete',
    ],
    'sheet' => [
        'title' => 'Edit',
        'close' => 'Close',
        'title_label' => 'Title',
        'list' => 'List',
        'size' => 'Size',
        'size_hint' => 'Tap again to clear it.',
        'notes' => 'Notes',
        'notes_placeholder' => 'Anything that does not fit in the title.',
        'save' => 'Save',
        'saving' => 'Saving …',
        'cancel' => 'Cancel',
        'delete' => 'Delete',
        'esc' => 'Esc closes',
        'later_no_hard' => 'Later has no hard deadline.',
    ],
    'toast' => [
        'saved' => '“:title” saved.',
        'moved' => '“:title” is now in :list.',
        'deleted' => '“:title” deleted.',
        'undo' => 'Undo',
        'edit_undone' => 'Change undone.',
        'delete_undone' => '“:title” is back.',
        'dismiss' => 'Close',
    ],
    'errors' => [
        'title' => 'Give the item a title.',
        'title_max' => 'At most :max characters. The rest fits in the notes.',
        'notes' => 'At most :max characters in the notes.',
        'list' => 'Choose Inbox, To-dos, Tasks or Later.',
        'locked' => 'This one is running in your session. End the session first.',
        'gone' => 'That item does not exist any more. The list is up to date.',
        'failed' => 'That did not work. Try again in a moment.',
    ],
];
