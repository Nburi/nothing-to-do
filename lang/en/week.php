<?php

// Step 4.1: the week plan editor (schedule, weekly plan) and the categories in the settings.
return [
    'undo' => 'Undo',
    'cancel' => 'Cancel',

    'colors' => [
        'forest' => 'Green',
        'contour' => 'Ochre',
        'overprint' => 'Magenta',
        'signal' => 'Red',
        'ink' => 'Ink',
    ],

    'categories' => [
        'title' => 'Categories',
        'intro' => 'Categories colour your blocks in the weekly plan. With "Pomodoro sessions" a category is work time: inside its blocks nothing-to-do suggests tasks from the areas you pick.',
        'empty' => 'No category yet. Add the first one below, for example "School" or "Deep work".',
        'name' => 'Name',
        'color' => 'Colour of :name',
        'colors' => 'Colours',
        'delete' => 'Delete :name',
        'deleted' => '":name" deleted. Its blocks keep their name and colour but no longer count as work time.',
        'restored' => '":name" is back.',
        'pomodoro' => 'Pomodoro sessions',
        'pomodoro_on' => 'work time',
        'pomodoro_off' => 'not work time',
        'suggest_from' => 'Suggest from',
        'no_areas' => 'No area: only tasks without an area come up here.',
        'new_area' => 'Area',
        'area_name' => 'Area name',
        'area_placeholder' => 'e.g. Own projects',
        'area_add' => 'Add',
        'new' => 'New category',
        'new_placeholder' => 'e.g. Deep work',
        'add' => 'Add',
    ],
];
