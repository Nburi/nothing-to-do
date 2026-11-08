<?php

namespace App\Console\Commands;

use App\Support\Rehearsal\SnapshotComparison;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Refactor spike 0.3: compare two rehearsal snapshots. Fails (exit code 1)
 * on anything that could mean lost or mangled data.
 *
 *   php artisan app:rehearsal-compare before after --same=tasks.deadline:tasks.hard_deadline
 */
class RehearsalCompare extends Command
{
    protected $signature = 'app:rehearsal-compare {before} {after}
        {--same=* : old:new column pair that must hold the same values, e.g. tasks.deadline:tasks.hard_deadline}
        {--allow-dropped=* : table or table.column that may be gone}
        {--ignore=* : table.column whose changes are expected}';

    protected $description = 'Compare two migration rehearsal snapshots and report anything lost';

    public function handle(): int
    {
        $before = $this->load($this->argument('before'));
        $after = $this->load($this->argument('after'));
        if ($before === null || $after === null) {
            return self::FAILURE;
        }

        $same = [];
        foreach ($this->option('same') as $pair) {
            [$old, $new] = array_pad(explode(':', $pair, 2), 2, null);
            if ($new === null) {
                $this->error("--same needs old:new, got {$pair}");

                return self::FAILURE;
            }
            $same[$old] = $new;
        }

        $result = SnapshotComparison::compare($before, $after, $same, $this->option('allow-dropped'), $this->option('ignore'));

        $this->line("Before: {$before['taken_at']} · last migration {$before['last_migration']}");
        $this->line("After:  {$after['taken_at']} · last migration {$after['last_migration']}");

        foreach ($result->notes as $note) {
            $this->line("  · {$note}");
        }
        foreach ($result->problems as $problem) {
            $this->error("  ✗ {$problem}");
        }

        if (isset($before['user'], $after['user'])) {
            $rows = [];
            foreach ($before['user']['tables'] as $table => $count) {
                $rows[] = [$table, $count, $after['user']['tables'][$table] ?? 'gone'];
            }
            $this->table(['Spot-check table', 'Before', 'After'], $rows);
        }

        if ($result->ok()) {
            $this->info('Nothing lost.');

            return self::SUCCESS;
        }

        $this->error(count($result->problems).' problem(s). Do not release this migration.');

        return self::FAILURE;
    }

    /** @return array<string, mixed>|null */
    private function load(string $name): ?array
    {
        $path = storage_path("app/rehearsal/{$name}.json");
        if (! File::exists($path)) {
            $this->error("No snapshot \"{$name}\" ({$path}). Run app:rehearsal-snapshot {$name} first.");

            return null;
        }

        return json_decode(File::get($path), true);
    }
}
