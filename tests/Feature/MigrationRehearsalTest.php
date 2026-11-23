<?php

namespace Tests\Feature;

use App\Support\Rehearsal\DatabaseSnapshot;
use App\Support\Rehearsal\SnapshotComparison;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\OldSchemaFixtures;
use Tests\TestCase;

/**
 * TESTBERICHT A12 (R8, risk C6): the refactor's migrations carry production's data over
 * without losing anything, and they roll back.
 *
 * It rehearses what Niels does on a copy of production (docs/spikes/0.3-migration-dry-run.md),
 * on invented fixture rows:
 *
 *   1. build the OLD schema: every migration up to the last one `main` has (see the constant),
 *   2. fill every table that holds user data (Tests\Support\OldSchemaFixtures),
 *   3. snapshot, run ALL newer migrations (whichever exist, nothing is listed here), snapshot,
 *   4. roll those back, snapshot.
 *
 * The snapshots and the comparison are the rehearsal tool's own (app:rehearsal-snapshot and
 * app:rehearsal-compare use the same classes). A newer migration that loses a row, changes an
 * old value or fails to roll back breaks this test. One that changes old data ON PURPOSE has to
 * say so in INTENDED_CHANGES and get an assertion of its own.
 *
 * The rehearsal runs once per test class (it migrates 100+ files); the tests below read its result.
 */
class MigrationRehearsalTest extends TestCase
{
    /**
     * The newest migration that also exists on `main`, so the schema production runs today.
     * Everything after it is the refactor's work. When `main` gets a migration of its own
     * (a hotfix), move this to it and add fixture rows for what it created.
     */
    private const LAST_PRODUCTION_MIGRATION = '2026_10_27_100002_add_reminder_settings_to_users_table';

    /** Old columns a newer migration changes on purpose: "table.column" => why. Any other change is a bug. */
    private const INTENDED_CHANGES = [
        'tasks.list' => '2026_10_31_000001 moves tasks out of the removed "projects" list into "tasks" (it cannot be undone).',
    ];

    /** The two-step renames (REFACTOR_PLAN § 3): the new column must hold exactly the old one's values. */
    private const RENAMES = [
        'tasks.deadline' => 'tasks.hard_deadline',
        'tasks.due_date' => 'tasks.soft_deadline',
    ];

    /** @var array<string, mixed>|null */
    private static ?array $rehearsal = null;

    private static ?\Throwable $failure = null;

    public function test_the_cutoff_names_a_real_migration(): void
    {
        $this->assertArrayHasKey(
            self::LAST_PRODUCTION_MIGRATION,
            $this->migrationFiles(),
            'LAST_PRODUCTION_MIGRATION no longer names a file in database/migrations.'
        );
    }

    public function test_every_table_that_holds_user_data_has_fixture_rows(): void
    {
        $empty = [];
        foreach ($this->rehearsal()['before']['tables'] as $table => $fingerprint) {
            if ($fingerprint['rows'] === 0 && ! in_array($table, OldSchemaFixtures::EMPTY_BY_DESIGN, true)) {
                $empty[] = $table;
            }
        }

        $this->assertSame([], $empty, 'Production tables without fixture rows can lose data unnoticed. Add rows to OldSchemaFixtures.');
    }

    public function test_the_newer_migrations_run_on_the_fixtures_without_losing_a_row_or_changing_an_old_value(): void
    {
        $rehearsal = $this->rehearsal();

        $result = SnapshotComparison::compare($rehearsal['before'], $rehearsal['after'], self::RENAMES, [], array_keys(self::INTENDED_CHANGES));

        $this->assertTrue($result->ok(), "Lost or changed data:\n".implode("\n", $result->problems));

        foreach ($rehearsal['before']['tables'] as $table => $old) {
            if ($table !== 'migrations') {
                $this->assertSame($old['rows'], $rehearsal['after']['tables'][$table]['rows'], "{$table}: the row count changed");
            }
        }
    }

    public function test_deadline_becomes_the_hard_deadline_and_due_date_the_soft_deadline_exactly(): void
    {
        $rehearsal = $this->rehearsal();
        $combinations = [];

        foreach (OldSchemaFixtures::taskRows() as $id => $fixture) {
            $deadline = $fixture['deadline'] ?? null;
            $dueDate = $fixture['due_date'] ?? null;
            $after = $rehearsal['tasksAfter'][$id];

            $this->assertSame($deadline, $after['hard_deadline'], "task {$id}: hard_deadline");
            $this->assertSame($dueDate, $after['soft_deadline'], "task {$id}: soft_deadline");
            // Two-step rename: the old columns stay until a later release drops them.
            $this->assertSame($deadline, $after['deadline'], "task {$id}: deadline must be untouched");
            $this->assertSame($dueDate, $after['due_date'], "task {$id}: due_date must be untouched");

            $combinations[($deadline !== null ? 'hard' : '-').'/'.($dueDate !== null ? 'soft' : '-')] = true;
        }

        $this->assertEqualsCanonicalizing(['hard/soft', 'hard/-', '-/soft', '-/-'], array_keys($combinations), 'the fixtures must cover every combination');

        $copies = SnapshotComparison::compare($rehearsal['before'], $rehearsal['after'], self::RENAMES, [], array_keys(self::INTENDED_CHANGES));
        $this->assertTrue($copies->ok(), implode("\n", $copies->problems));
    }

    public function test_tasks_in_the_removed_projects_list_move_into_tasks_and_no_other_list_changes(): void
    {
        $rehearsal = $this->rehearsal();
        $moved = 0;

        foreach (OldSchemaFixtures::taskRows() as $id => $fixture) {
            $expected = $fixture['list'] === 'projects' ? 'tasks' : $fixture['list'];
            $moved += $fixture['list'] === 'projects' ? 1 : 0;

            $this->assertSame($expected, $rehearsal['tasksAfter'][$id]['list'], "task {$id}: list");
            $this->assertSame($fixture['project_id'] ?? null, $rehearsal['tasksAfter'][$id]['project_id'], "task {$id}: project_id still marks the project");
        }

        $this->assertGreaterThan(0, $moved, 'the fixtures must contain tasks in the projects list');
    }

    public function test_rolling_the_newer_migrations_back_restores_the_old_schema_and_data(): void
    {
        $rehearsal = $this->rehearsal();
        $before = $rehearsal['before']['tables'];
        $rolledBack = $rehearsal['rolledBack']['tables'];

        $this->assertSame(array_keys($before), array_keys($rolledBack), 'tables left over or missing after the rollback');
        foreach ($before as $table => $old) {
            $this->assertSame($old['columns'], $rolledBack[$table]['columns'], "{$table}: columns differ after the rollback");
            $this->assertSame($old['rows'], $rolledBack[$table]['rows'], "{$table}: the row count differs after the rollback");
        }

        $result = SnapshotComparison::compare($rehearsal['before'], $rehearsal['rolledBack'], [], [], array_keys(self::INTENDED_CHANGES));
        $this->assertTrue($result->ok(), "The rollback did not restore the old data:\n".implode("\n", $result->problems));
        $this->assertSame($before['migrations'], $rolledBack['migrations'], 'the migrations table must list the old migrations again');

        // The one documented gap: a task moved out of "projects" stays in "tasks" (the original
        // list is not stored). Nothing is lost and project_id still marks it, which is what counts here.
        foreach (OldSchemaFixtures::taskRows() as $id => $fixture) {
            if ($fixture['list'] === 'projects') {
                $this->assertContains($rehearsal['tasksRolledBack'][$id]['list'], ['projects', 'tasks'], "task {$id}: list");
                $this->assertSame($fixture['project_id'], $rehearsal['tasksRolledBack'][$id]['project_id'], "task {$id}: project_id");
            }
        }
    }

    /** The rehearsal runs once; a failure is remembered so the other tests do not repeat a slow, failing run. */
    private function rehearsal(): array
    {
        if (self::$failure !== null) {
            throw self::$failure;
        }

        try {
            return self::$rehearsal ??= $this->rehearse();
        } catch (\Throwable $e) {
            self::$failure = $e;

            throw $e;
        }
    }

    /** @return array<string, mixed> */
    private function rehearse(): array
    {
        $files = $this->migrationFiles();
        $this->assertArrayHasKey(self::LAST_PRODUCTION_MIGRATION, $files, 'LAST_PRODUCTION_MIGRATION no longer names a file in database/migrations.');

        $old = array_filter($files, fn (string $path, string $name) => strcmp($name, self::LAST_PRODUCTION_MIGRATION) <= 0, ARRAY_FILTER_USE_BOTH);
        $newer = array_diff_key($files, $old);

        if ($newer === []) {
            $this->markTestSkipped('No migration is newer than production: nothing to rehearse.');
        }

        // 1. Production's schema, nothing else.
        $this->migrate(['--path' => array_values($old), '--realpath' => true]);
        $this->assertTrue(Schema::hasColumn('tasks', 'deadline') && ! Schema::hasColumn('tasks', 'hard_deadline'), 'the old schema must not have the new columns yet');

        // 2. Production's data.
        OldSchemaFixtures::insert();
        $snapshots = new DatabaseSnapshot;
        $before = $snapshots->take();
        $tasksBefore = $this->tasks();

        // 3. The refactor's migrations: whatever is pending now, exactly like `php artisan migrate`.
        $this->migrate();
        $ran = DB::table('migrations')->where('batch', '>', 1)->orderBy('id')->pluck('migration')->all();
        $this->assertSame(array_keys($newer), $ran, 'migrate must have run exactly the migrations newer than production');
        $after = $snapshots->take();
        $tasksAfter = $this->tasks();

        // 4. And back, like `php artisan migrate:rollback` (the last batch is all of them).
        $exit = Artisan::call('migrate:rollback', ['--force' => true]);
        $this->assertSame(0, $exit, Artisan::output());
        $rolledBack = $snapshots->take();

        return [
            'before' => $before,
            'after' => $after,
            'rolledBack' => $rolledBack,
            'tasksBefore' => $tasksBefore,
            'tasksAfter' => $tasksAfter,
            'tasksRolledBack' => $this->tasks(),
        ];
    }

    /** @param  array<string, mixed>  $options */
    private function migrate(array $options = []): void
    {
        $exit = Artisan::call('migrate', $options + ['--force' => true]);

        $this->assertSame(0, $exit, Artisan::output());
    }

    /** @return array<string, string> migration name => absolute path, in the order Laravel runs them */
    private function migrationFiles(): array
    {
        $files = [];
        foreach (glob(database_path('migrations').DIRECTORY_SEPARATOR.'*.php') ?: [] as $path) {
            $files[basename($path, '.php')] = $path;
        }
        ksort($files, SORT_STRING);

        return $files;
    }

    /** @return array<int, array<string, mixed>> */
    private function tasks(): array
    {
        return DB::table('tasks')->orderBy('id')->get()->mapWithKeys(fn (object $row) => [$row->id => (array) $row])->all();
    }
}
