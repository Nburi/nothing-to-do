<?php

namespace Tests\Feature\Commands;

use App\Models\Task;
use App\Models\User;
use App\Support\Rehearsal\DatabaseSnapshot;
use App\Support\Rehearsal\SnapshotComparison;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Spike 0.3 (R8, risk C6): the tool that proves a migration lost nothing.
 * Snapshot before, migrate, snapshot after, compare. Snapshots hold counts
 * and per-column checksums only, never the data itself.
 */
class MigrationRehearsalToolTest extends TestCase
{
    use RefreshDatabase;

    private DatabaseSnapshot $snapshots;

    protected function setUp(): void
    {
        parent::setUp();
        $this->snapshots = new DatabaseSnapshot;
        $user = User::factory()->create(['email' => 'niels@example.test']);
        Task::factory()->for($user)->count(3)->create();
        Task::factory()->count(2)->create();
    }

    public function test_an_unchanged_database_compares_clean(): void
    {
        $before = $this->snapshots->take();
        $after = $this->snapshots->take();

        $result = SnapshotComparison::compare($before, $after);

        $this->assertTrue($result->ok());
        $this->assertSame([], $result->problems);
    }

    public function test_a_lost_row_is_a_problem(): void
    {
        $before = $this->snapshots->take();
        Task::query()->first()->delete();

        $result = SnapshotComparison::compare($before, $this->snapshots->take());

        $this->assertFalse($result->ok());
        $this->assertContains('tasks: 5 rows before, 4 after', $result->problems);
    }

    public function test_a_changed_value_in_an_old_column_is_a_problem(): void
    {
        $before = $this->snapshots->take();
        DB::table('tasks')->where('id', Task::query()->min('id'))->update(['title' => 'Überschrieben']);

        $result = SnapshotComparison::compare($before, $this->snapshots->take());

        $this->assertContains('tasks.title: values changed', $result->problems);
    }

    public function test_a_new_table_and_a_new_column_are_reported_but_fine(): void
    {
        $before = $this->snapshots->take();
        Schema::create('areas', fn ($t) => $t->id());
        Schema::table('tasks', fn ($t) => $t->string('size')->nullable());

        $result = SnapshotComparison::compare($before, $this->snapshots->take());

        $this->assertTrue($result->ok(), implode("\n", $result->problems));
        $this->assertContains('areas: new table (0 rows)', $result->notes);
        $this->assertContains('tasks.size: new column', $result->notes);
    }

    public function test_a_dropped_column_is_a_problem_unless_allowed(): void
    {
        $before = $this->snapshots->take();
        Schema::table('tasks', fn ($t) => $t->dropColumn('notes'));
        $after = $this->snapshots->take();

        $this->assertContains('tasks.notes: column dropped', SnapshotComparison::compare($before, $after)->problems);
        $this->assertTrue(SnapshotComparison::compare($before, $after, allowDropped: ['tasks.notes'])->ok());
    }

    public function test_a_copied_column_must_match_its_source(): void
    {
        // The two-step rename from the plan: deadline -> hard_deadline.
        DB::table('tasks')->update(['deadline' => '2026-11-20']);
        $before = $this->snapshots->take();
        Schema::table('tasks', fn ($t) => $t->date('hard_deadline')->nullable());
        DB::table('tasks')->update(['hard_deadline' => DB::raw('deadline')]);
        $after = $this->snapshots->take();

        $this->assertTrue(SnapshotComparison::compare($before, $after, same: ['tasks.deadline' => 'tasks.hard_deadline'])->ok());

        DB::table('tasks')->where('id', Task::query()->min('id'))->update(['hard_deadline' => null]);
        $broken = SnapshotComparison::compare($before, $this->snapshots->take(), same: ['tasks.deadline' => 'tasks.hard_deadline']);
        $this->assertContains('tasks.hard_deadline does not match tasks.deadline from before', $broken->problems);
    }

    public function test_the_spot_check_counts_one_users_rows(): void
    {
        $snapshot = $this->snapshots->take(User::where('email', 'niels@example.test')->sole());

        $this->assertSame(3, $snapshot['user']['tables']['tasks']);
        $this->assertSame(5, $snapshot['tables']['tasks']['rows']);
    }

    public function test_a_snapshot_never_contains_the_data_itself(): void
    {
        DB::table('tasks')->update(['title' => 'Geheimer Titel']);

        $json = json_encode($this->snapshots->take(User::first()));

        $this->assertStringNotContainsString('Geheimer Titel', $json);
        $this->assertStringNotContainsString('niels@example.test', $json);
    }

    public function test_the_commands_write_and_compare_snapshot_files(): void
    {
        $dir = storage_path('app/rehearsal');
        File::deleteDirectory($dir);

        $this->artisan('app:rehearsal-snapshot', ['name' => 'before', '--user' => 'niels@example.test'])->assertSuccessful();
        $this->assertFileExists($dir.'/before.json');

        Task::query()->first()->delete();
        $this->artisan('app:rehearsal-snapshot', ['name' => 'after'])->assertSuccessful();

        $this->artisan('app:rehearsal-compare', ['before' => 'before', 'after' => 'after'])
            ->expectsOutputToContain('tasks: 5 rows before, 4 after')
            ->assertFailed();

        $this->artisan('app:rehearsal-compare', ['before' => 'before', 'after' => 'before'])
            ->expectsOutputToContain('Nothing lost')
            ->assertSuccessful();

        File::deleteDirectory($dir);
    }

    public function test_compare_explains_a_missing_snapshot_file(): void
    {
        $this->artisan('app:rehearsal-compare', ['before' => 'nope', 'after' => 'nope2'])
            ->expectsOutputToContain('No snapshot')
            ->assertFailed();
    }
}
