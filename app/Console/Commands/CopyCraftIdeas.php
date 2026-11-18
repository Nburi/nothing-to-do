<?php

namespace App\Console\Commands;

use App\Services\Planning\CraftIdeaCopier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Refactor step 3.4: copy the old Bastelideen into Later (see CraftIdeaCopier). Meant for the
 * switch (step 5.4): run with --dry-run first, then without. Safe to run twice; never changes or
 * deletes craft_ideas. Not scheduled.
 */
class CopyCraftIdeas extends Command
{
    protected $signature = 'app:copy-craft-ideas
        {--dry-run : only count, write nothing}
        {--user= : only this user id}';

    protected $description = 'Copy every old craft idea (Bastelidee) once into a Later task with its "where to begin" note';

    public function handle(CraftIdeaCopier $copier): int
    {
        $user = $this->option('user');
        if ($user !== null && ! ctype_digit((string) $user)) {
            $this->error('--user takes a user id (a number).');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $this->line('Database: <comment>'.DB::connection()->getDatabaseName().'</comment> ('.DB::connection()->getDriverName().')'.($dry ? ' · <info>dry run, nothing is written</info>' : ''));

        $stats = $copier->run($dry, $user === null ? null : (int) $user);

        $this->table(['users', 'craft ideas', 'already copied', $dry ? 'would copy' : 'copied', 'long notes kept in notes'], [[
            $stats['users'], $stats['ideas'], $stats['already'], $stats['copied'], $stats['long_notes'],
        ]]);
        $this->line('craft_ideas was not changed.');

        return self::SUCCESS;
    }
}
