<?php

namespace Tests\Feature\Refactor;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A39 (step 4.3, R8): a group may hold only one list now. Production groups whose open tasks
 * sit in more than one list become a project at the switch. Nothing moves, nothing is deleted:
 * the group row and its note cards stay, the tasks keep their list and gain the project.
 */
class MixedGroupsToProjectsTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_11_17_000004_turn_mixed_groups_into_projects.php');
    }

    private function group(User $user, string $name, array $notes = []): int
    {
        $id = DB::table('task_groups')->insertGetId(['user_id' => $user->id, 'name' => $name, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
        foreach ($notes as $i => $note) {
            DB::table('group_notes')->insert(['task_group_id' => $id, 'content' => $note, 'sort_order' => $i, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $id;
    }

    private function task(User $user, int $groupId, string $list, bool $done = false): Task
    {
        return Task::forceCreate(['user_id' => $user->id, 'title' => "$list task", 'list' => $list, 'group_id' => $groupId, 'is_completed' => $done]);
    }

    public function test_a_group_with_open_tasks_in_two_lists_becomes_a_project_and_keeps_everything(): void
    {
        $user = User::factory()->create();
        $groupId = $this->group($user, 'Zimmer', ['Kartons besorgen', 'Maße notieren']);
        $todo = $this->task($user, $groupId, 'todos');
        $task = $this->task($user, $groupId, 'tasks');
        $done = $this->task($user, $groupId, 'inbox', done: true);

        $this->migration()->up();

        $project = DB::table('projects')->where('user_id', $user->id)->first();
        $this->assertNotNull($project);
        $this->assertSame('Zimmer', $project->name);
        $this->assertSame("Kartons besorgen\n\nMaße notieren", $project->brainstorm);
        $this->assertSame($project->id, DB::table('task_groups')->where('id', $groupId)->value('project_id'));

        foreach ([[$todo, 'todos'], [$task, 'tasks'], [$done, 'inbox']] as [$t, $list]) {
            $fresh = $t->fresh();
            $this->assertSame($project->id, $fresh->project_id);
            $this->assertSame($groupId, $fresh->group_id, 'group_id stays, so the old link can still be read');
            $this->assertSame($list, $fresh->list, 'nothing moves between lists');
        }

        $this->assertSame(2, DB::table('group_notes')->where('task_group_id', $groupId)->count(), 'note cards are not deleted');
    }

    public function test_a_group_with_one_list_stays_a_group(): void
    {
        $user = User::factory()->create();
        $groupId = $this->group($user, 'Notion');
        $a = $this->task($user, $groupId, 'tasks');
        $this->task($user, $groupId, 'tasks');
        $this->task($user, $groupId, 'todos', done: true); // finished work does not make it mixed

        $this->migration()->up();

        $this->assertSame(0, DB::table('projects')->count());
        $this->assertNull(DB::table('task_groups')->where('id', $groupId)->value('project_id'));
        $this->assertSame('tasks', DB::table('task_groups')->where('id', $groupId)->value('list'), 'the group remembers its one list');
        $this->assertNull($a->fresh()->project_id);
    }

    public function test_a_finished_group_takes_the_list_of_its_newest_task(): void
    {
        $user = User::factory()->create();
        $groupId = $this->group($user, 'Erledigt');
        $this->task($user, $groupId, 'tasks', done: true);
        $this->task($user, $groupId, 'todos', done: true);
        $empty = $this->group($user, 'Leer');

        $this->migration()->up();

        $this->assertSame('todos', DB::table('task_groups')->where('id', $groupId)->value('list'));
        $this->assertSame('tasks', DB::table('task_groups')->where('id', $empty)->value('list'));
    }

    public function test_a_task_that_already_belongs_to_a_project_keeps_it(): void
    {
        $user = User::factory()->create();
        $old = DB::table('projects')->insertGetId(['user_id' => $user->id, 'name' => 'Maturarbeit', 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $groupId = $this->group($user, 'Kapitel');
        $mine = $this->task($user, $groupId, 'todos');
        DB::table('tasks')->where('id', $mine->id)->update(['project_id' => $old]);
        $this->task($user, $groupId, 'tasks');
        $this->task($user, $groupId, 'todos');

        $this->migration()->up();

        $this->assertSame($old, $mine->fresh()->project_id);
    }

    public function test_it_runs_twice_without_creating_a_second_project_and_rolls_back(): void
    {
        $user = User::factory()->create();
        $groupId = $this->group($user, 'Umzug');
        $todo = $this->task($user, $groupId, 'todos');
        $this->task($user, $groupId, 'tasks');

        $this->migration()->up();
        $this->migration()->up();
        $this->assertSame(1, DB::table('projects')->count());

        $this->migration()->down();
        $this->assertSame(0, DB::table('projects')->count());
        $this->assertNull($todo->fresh()->project_id);
        $this->assertSame($groupId, $todo->fresh()->group_id);
        $this->assertNull(DB::table('task_groups')->where('id', $groupId)->value('project_id'));
    }

    public function test_groups_of_different_users_stay_apart(): void
    {
        [$a, $b] = User::factory()->count(2)->create();
        $ga = $this->group($a, 'A');
        $this->task($a, $ga, 'todos');
        $this->task($a, $ga, 'tasks');
        $gb = $this->group($b, 'B');
        $this->task($b, $gb, 'tasks');

        $this->migration()->up();

        $this->assertSame([$a->id], DB::table('projects')->pluck('user_id')->all());
    }
}
