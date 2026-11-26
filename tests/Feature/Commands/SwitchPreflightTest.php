<?php

namespace Tests\Feature\Commands;

use App\Models\Area;
use App\Models\CraftIdea;
use App\Models\EventCategory;
use App\Models\Task;
use App\Models\User;
use App\Support\Rehearsal\DatabaseSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Step 5.4: `app:switch-preflight` is the read-only data check before the switch. It reports row
 * counts, orphans and the open decisions, and exits non-zero only for what blocks the switch.
 */
class SwitchPreflightTest extends TestCase
{
    use RefreshDatabase;

    private function group(User $user, string $name = 'Gruppe'): int
    {
        return DB::table('task_groups')->insertGetId(['user_id' => $user->id, 'name' => $name, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function project(User $user, string $name = 'Projekt'): int
    {
        return DB::table('projects')->insertGetId(['user_id' => $user->id, 'name' => $name, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** Orphans can only exist where foreign keys are not enforced; SQLite checks them at commit, which a test never reaches. */
    private function allowOrphans(): void
    {
        DB::statement('PRAGMA defer_foreign_keys = ON');
    }

    public function test_a_clean_database_passes_with_every_check_ok(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->todos()->create();

        $this->artisan('app:switch-preflight')
            ->expectsOutputToContain('Nothing blocks the switch')
            ->assertSuccessful();

        $report = app(\App\Support\Release\SwitchPreflight::class)->run();
        $this->assertSame(1, $report['counts']['tasks']);
        $this->assertSame(1, $report['counts']['users']);
        $this->assertArrayNotHasKey('sessions', $report['counts'], 'volatile tables are left out');
        $this->assertSame([], array_filter($report['checks'], fn ($c) => $c['count'] > 0));
    }

    public function test_a_task_without_its_user_blocks_the_switch(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->todos()->create();
        $this->allowOrphans();
        DB::table('tasks')->where('id', $task->id)->update(['user_id' => 987654]);

        $this->artisan('app:switch-preflight')
            ->expectsOutputToContain("tasks.user_id points at a users row that does not exist (ids {$task->id})")
            ->expectsOutputToContain('BLOCKED')
            ->assertFailed();
    }

    public function test_tasks_pointing_at_a_missing_area_group_project_or_stage_block_the_switch(): void
    {
        $user = User::factory()->create();
        $this->allowOrphans();
        foreach (['area_id', 'group_id', 'project_id', 'stage_id'] as $column) {
            $task = Task::factory()->for($user)->todos()->create();
            DB::table('tasks')->where('id', $task->id)->update([$column => 555000]);
        }

        $report = app(\App\Support\Release\SwitchPreflight::class)->run();
        $found = collect($report['checks'])->where('level', 'block')->where('count', 1)->pluck('key')->all();

        $this->assertEqualsCanonicalizing(
            ['orphan.tasks.area_id', 'orphan.tasks.group_id', 'orphan.tasks.project_id', 'orphan.tasks.stage_id'],
            $found
        );
        $this->artisan('app:switch-preflight')->assertFailed();
    }

    public function test_a_task_pointing_at_another_users_area_group_or_project_blocks_the_switch(): void
    {
        $mine = User::factory()->create();
        $other = User::factory()->create();
        $area = Area::factory()->for($other)->create();
        Task::factory()->for($mine)->todos()->create(['area_id' => $area->id]);
        Task::factory()->for($mine)->todos()->create(['group_id' => $this->group($other)]);
        Task::factory()->for($mine)->todos()->create(['project_id' => $this->project($other)]);
        Task::factory()->for($other)->todos()->create(['area_id' => $area->id]);

        $report = app(\App\Support\Release\SwitchPreflight::class)->run();
        $found = collect($report['checks'])->where('level', 'block')->where('count', 1)->pluck('key')->all();

        $this->assertEqualsCanonicalizing(['foreign.tasks.area_id', 'foreign.tasks.group_id', 'foreign.tasks.project_id'], $found);
        $this->artisan('app:switch-preflight')->assertFailed();
    }

    public function test_a_group_with_open_tasks_in_two_lists_is_a_note_not_a_blocker(): void
    {
        $user = User::factory()->create();
        $mixed = $this->group($user, 'Mixed');
        $single = $this->group($user, 'Single');
        $finishedMix = $this->group($user, 'Finished mix');
        Task::factory()->for($user)->todos()->create(['group_id' => $mixed]);
        Task::factory()->for($user)->tasks()->create(['group_id' => $mixed]);
        Task::factory()->for($user)->todos()->create(['group_id' => $single]);
        Task::factory()->for($user)->todos()->create(['group_id' => $single]);
        Task::factory()->for($user)->todos()->create(['group_id' => $finishedMix]);
        Task::factory()->for($user)->tasks()->completed()->create(['group_id' => $finishedMix]);

        $this->artisan('app:switch-preflight')
            ->expectsOutputToContain("1 × groups with open tasks in several lists (each becomes a project at migrate) (ids {$mixed})")
            ->assertSuccessful();
    }

    public function test_after_the_migration_a_converted_group_is_no_longer_reported(): void
    {
        $user = User::factory()->create();
        $mixed = $this->group($user);
        Task::factory()->for($user)->todos()->create(['group_id' => $mixed]);
        Task::factory()->for($user)->tasks()->create(['group_id' => $mixed]);
        $projectId = $this->project($user);
        DB::table('task_groups')->where('id', $mixed)->update(['project_id' => $projectId]);
        DB::table('tasks')->where('group_id', $mixed)->update(['project_id' => $projectId]);

        $report = app(\App\Support\Release\SwitchPreflight::class)->run();

        $this->assertSame(0, collect($report['checks'])->firstWhere('key', 'groups.mixed')['count']);
    }

    public function test_categories_linked_to_a_project_group_or_agenda_entry_are_a_warning(): void
    {
        $user = User::factory()->create();
        EventCategory::factory()->for($user)->create(['task_source' => 'project']);
        EventCategory::factory()->for($user)->create(['task_source' => 'group']);
        EventCategory::factory()->for($user)->create(['task_source' => 'agenda_entry']);
        EventCategory::factory()->for($user)->create(['task_source' => 'agenda_generic']);
        EventCategory::factory()->for($user)->create(['task_source' => 'tasks']);
        EventCategory::factory()->for($user)->create(['task_source' => 'text', 'linked_text' => 'Lesen']);
        EventCategory::factory()->for($user)->create(['task_source' => null]);
        EventCategory::factory()->for($user)->create(['task_source' => 'project'])->delete();

        $this->artisan('app:switch-preflight')
            ->expectsOutputToContain('4 × categories linked to a project, group or Agenda entry')
            ->assertSuccessful();
    }

    public function test_users_with_many_open_important_marks_are_a_warning_and_the_limit_is_an_option(): void
    {
        $many = User::factory()->create();
        $ten = User::factory()->create();
        Task::factory()->for($many)->todos()->important()->count(11)->create();
        Task::factory()->for($many)->todos()->important()->completed()->count(5)->create();
        Task::factory()->for($ten)->todos()->important()->count(10)->create();

        $this->artisan('app:switch-preflight')
            ->expectsOutputToContain("1 × users with more than 10 open tasks marked as Wichtig (decision pending) (user {$many->id}: 11)")
            ->assertSuccessful();

        $this->artisan('app:switch-preflight', ['--important-limit' => 9])
            ->expectsOutputToContain('2 × users with more than 9 open tasks marked as Wichtig')
            ->assertSuccessful();

        $this->artisan('app:switch-preflight', ['--important-limit' => 'many'])->assertExitCode(2);
    }

    public function test_craft_ideas_not_copied_yet_are_counted_and_copied_ones_are_not(): void
    {
        $user = User::factory()->create();
        $copied = CraftIdea::factory()->for($user)->create();
        CraftIdea::factory()->for($user)->count(2)->create();
        DB::table('craft_idea_copies')->insert(['craft_idea_id' => $copied->id, 'user_id' => $user->id, 'task_id' => null, 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('app:switch-preflight')
            ->expectsOutputToContain('2 × craft ideas not copied into Later yet')
            ->assertSuccessful();
    }

    public function test_the_retired_projects_list_and_pending_migrations_are_notes(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->create(['list' => 'projects']);
        DB::table('migrations')->where('migration', '2026_11_23_000001_add_moved_from_id_to_schedule_events_table')->delete();

        $this->artisan('app:switch-preflight')
            ->expectsOutputToContain('1 × tasks in the retired list "projects"')
            ->expectsOutputToContain('1 × migrations not run yet')
            ->assertSuccessful();
    }

    public function test_it_works_on_the_old_schema_before_the_new_tables_exist(): void
    {
        $user = User::factory()->create();
        CraftIdea::factory()->for($user)->count(2)->create();
        Schema::dropIfExists('craft_idea_copies');
        Schema::dropIfExists('notes');
        Schema::dropColumns('event_categories', ['deleted_at']);

        $this->artisan('app:switch-preflight')
            ->expectsOutputToContain('2 × craft ideas not copied into Later yet')
            ->assertSuccessful();
    }

    public function test_it_never_writes_even_when_it_finds_problems(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->todos()->create();
        $this->allowOrphans();
        DB::table('tasks')->where('id', $task->id)->update(['user_id' => 987654]);
        $this->group($user);
        $before = $this->tableCounts();

        // With query_only on, SQLite refuses every write: an INSERT, UPDATE, DELETE or DDL would throw.
        DB::statement('PRAGMA query_only = ON');
        try {
            $this->artisan('app:switch-preflight')->assertFailed();
        } finally {
            DB::statement('PRAGMA query_only = OFF');
        }

        $this->assertSame($before, $this->tableCounts());
    }

    /** @return array<string, int> */
    private function tableCounts(): array
    {
        $counts = [];
        foreach (Schema::getTables() as $table) {
            if (! in_array($table['name'], DatabaseSnapshot::VOLATILE_TABLES, true)) {
                $counts[$table['name']] = DB::table($table['name'])->count();
            }
        }

        return $counts;
    }
}
