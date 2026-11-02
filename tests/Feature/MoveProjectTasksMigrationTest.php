<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MoveProjectTasksMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function runMigration(): void
    {
        $migration = require database_path('migrations/2026_10_31_000001_move_project_tasks_to_the_tasks_list.php');
        $migration->up();
    }

    public function test_tasks_from_the_removed_projects_list_move_into_tasks_and_stay_visible(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create(['title' => 'Kapitel 1']);
        DB::table('tasks')->where('id', $task->id)->update(['list' => 'projects']);

        $this->runMigration();

        $this->assertSame('tasks', $task->fresh()->list);
    }

    public function test_other_lists_are_left_alone_and_nothing_is_deleted(): void
    {
        $user = User::factory()->create();
        $inbox = Task::factory()->for($user)->create(['list' => 'inbox']);
        $todo = Task::factory()->for($user)->todos()->create();

        $this->runMigration();

        $this->assertSame('inbox', $inbox->fresh()->list);
        $this->assertSame('todos', $todo->fresh()->list);
        $this->assertSame(2, Task::count());
    }
}
