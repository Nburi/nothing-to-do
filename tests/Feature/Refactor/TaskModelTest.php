<?php

namespace Tests\Feature\Refactor;

use App\Models\Area;
use App\Models\Task;
use App\Models\User;
use App\Planning\Size;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Refactor step 1.1 (R5, R8): size, hard and soft deadline, area on the task,
 * added as the first half of a two-step rename with no data lost.
 */
class TaskModelTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_11_07_000002_add_size_deadlines_and_area_to_tasks_table.php');
    }

    public function test_the_migration_copies_existing_deadlines_without_losing_a_row(): void
    {
        $user = User::factory()->create();
        $migration = $this->migration();
        $migration->down();

        // Rows as production has them today: only the old columns exist.
        $rows = [
            ['deadline' => '2026-11-20', 'due_date' => null],
            ['deadline' => null, 'due_date' => '2026-11-12'],
            ['deadline' => '2026-12-01', 'due_date' => '2026-11-25'],
            ['deadline' => null, 'due_date' => null],
        ];
        foreach ($rows as $i => $dates) {
            DB::table('tasks')->insert($dates + [
                'user_id' => $user->id,
                'title' => "Alt {$i}",
                'list' => 'tasks',
                'created_at' => now(),
                'updated_at' => '2026-01-01 00:00:00',
            ]);
        }

        $migration->up();

        $this->assertSame(4, DB::table('tasks')->count());
        foreach (DB::table('tasks')->orderBy('id')->get() as $i => $row) {
            $this->assertSame($rows[$i]['deadline'], $row->hard_deadline ? substr($row->hard_deadline, 0, 10) : null);
            $this->assertSame($rows[$i]['due_date'], $row->soft_deadline ? substr($row->soft_deadline, 0, 10) : null);
            // The old columns are untouched and the backfill did not bump updated_at.
            $this->assertSame($rows[$i]['deadline'], $row->deadline ? substr($row->deadline, 0, 10) : null);
            $this->assertSame('2026-01-01 00:00:00', $row->updated_at);
            $this->assertNull($row->size, 'existing tasks stay "not sized yet"');
        }
    }

    public function test_writing_an_old_column_mirrors_into_the_new_one(): void
    {
        $task = Task::factory()->create(['deadline' => '2026-11-20', 'due_date' => '2026-11-15']);

        $this->assertSame('2026-11-20', $task->fresh()->hard_deadline->toDateString());
        $this->assertSame('2026-11-15', $task->fresh()->soft_deadline->toDateString());

        $task->update(['deadline' => null]);
        $this->assertNull($task->fresh()->hard_deadline);
    }

    public function test_writing_a_new_column_mirrors_back_into_the_old_one(): void
    {
        $task = Task::factory()->create();
        $task->update(['hard_deadline' => '2026-11-30', 'soft_deadline' => '2026-11-28']);

        $fresh = $task->fresh();
        $this->assertSame('2026-11-30', $fresh->deadline->toDateString());
        $this->assertSame('2026-11-28', $fresh->due_date->toDateString());
    }

    public function test_the_new_column_wins_when_both_sides_disagree_in_one_write(): void
    {
        $task = Task::factory()->create();
        $task->update(['deadline' => '2026-11-01', 'hard_deadline' => '2026-11-09']);

        $fresh = $task->fresh();
        $this->assertSame('2026-11-09', $fresh->hard_deadline->toDateString());
        $this->assertSame('2026-11-09', $fresh->deadline->toDateString());
    }

    public function test_reads_go_through_the_new_columns(): void
    {
        $task = Task::factory()->create(['due_date' => '2026-11-15']);

        $this->assertSame('2026-11-15', $task->effectiveDate()->toDateString());
        $this->assertFalse($task->isUrgencyLocked());

        $task->update(['hard_deadline' => '2026-11-10']);
        $this->assertSame('2026-11-10', $task->fresh()->effectiveDate()->toDateString());
        $this->assertTrue($task->fresh()->isUrgencyLocked());
    }

    public function test_reads_work_on_an_unsaved_model_filled_through_either_column(): void
    {
        $old = Task::factory()->make(['deadline' => '2026-11-10']);
        $this->assertTrue($old->isUrgencyLocked());
        $this->assertSame('2026-11-10', $old->effectiveDate()->toDateString());

        $new = Task::factory()->make(['soft_deadline' => '2026-11-12']);
        $this->assertFalse($new->isUrgencyLocked());
        $this->assertSame('2026-11-12', $new->effectiveDate()->toDateString());
    }

    public function test_size_is_cast_to_the_engine_enum_and_null_counts_as_short(): void
    {
        $unsized = Task::factory()->create();
        $this->assertNull($unsized->fresh()->size);
        $this->assertSame(Size::Short, $unsized->fresh()->effectiveSize());

        $quick = Task::factory()->create(['size' => 'quick']);
        $this->assertSame(Size::Quick, $quick->fresh()->size);
    }

    public function test_an_unknown_size_is_rejected_instead_of_stored(): void
    {
        $this->expectException(\ValueError::class);

        Task::factory()->create(['size' => 'enormous'])->fresh()->size;
    }

    public function test_to_planned_task_carries_what_the_engine_needs(): void
    {
        $user = User::factory()->create();
        $area = Area::factory()->for($user)->create();
        $task = Task::factory()->for($user)->create([
            'size' => 'block',
            'duration_minutes' => 130, // 5.2 sessions → 6
            'hard_deadline' => '2026-11-20',
            'soft_deadline' => '2026-11-18',
            'area_id' => $area->id,
        ]);

        $planned = $task->fresh()->toPlannedTask();

        $this->assertSame($task->id, $planned->id);
        $this->assertSame(Size::Block, $planned->size);
        $this->assertSame(6, $planned->estimatedSessions);
        $this->assertSame('2026-11-20', $planned->hardDeadline);
        $this->assertSame('2026-11-18', $planned->softDeadline);
        $this->assertSame($area->id, $planned->areaId);
        $this->assertSame(6 * 25, $planned->remainingMinutes());
        $this->assertSame('2026-11-18', $planned->bindingDeadline());
    }

    public function test_to_planned_task_for_an_unsized_undated_task_is_one_short_session(): void
    {
        $planned = Task::factory()->create()->fresh()->toPlannedTask();

        $this->assertSame(Size::Short, $planned->size);
        $this->assertNull($planned->bindingDeadline());
        $this->assertNull($planned->areaId);
        $this->assertSame(25, $planned->remainingMinutes());
    }
}
