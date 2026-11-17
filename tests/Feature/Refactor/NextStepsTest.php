<?php

namespace Tests\Feature\Refactor;

use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Task;
use App\Models\TaskGroup;
use App\Models\User;
use App\Services\Projects\Bundle;
use App\Services\Projects\NextSteps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A40 (projects) and A41 (groups), step 4.3 (R6, risk F10): the board shows at most the next two
 * steps of a project or group, never a wall. For projects "next" means: in To-dos or Tasks, in the
 * current stage (or in no stage), and not waiting on an open task.
 */
class NextStepsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function project(string $name = 'Enderdragon'): Project
    {
        $p = new Project(['name' => $name]);
        $p->user_id = $this->user->id;
        $p->save();

        return $p;
    }

    private function stage(Project $p, string $name, int $order): ProjectStage
    {
        return $p->stages()->create(['name' => $name, 'sort_order' => $order]);
    }

    private function step(string $title, array $attrs = []): Task
    {
        return Task::forceCreate(['user_id' => $this->user->id, 'title' => $title, 'list' => 'tasks', ...$attrs]);
    }

    private function bundle(string $kind, int $id): Bundle
    {
        return app(NextSteps::class)->bundles($this->user)->first(fn (Bundle $b) => $b->kind === $kind && $b->id === $id);
    }

    private function titles(Bundle $b): array
    {
        return $b->visible->pluck('title')->all();
    }

    public function test_a_project_shows_its_first_two_steps_in_its_order(): void
    {
        $p = $this->project();
        $this->step('C', ['project_id' => $p->id, 'sort_order' => 2]);
        $this->step('A', ['project_id' => $p->id, 'sort_order' => 0]);
        $this->step('B', ['project_id' => $p->id, 'sort_order' => 1, 'list' => 'todos']);
        $this->step('D', ['project_id' => $p->id, 'sort_order' => 3]);

        $b = $this->bundle('project', $p->id);

        $this->assertSame(['A', 'B'], $this->titles($b));
        $this->assertSame(2, $b->more);
        $this->assertSame('tasks', $b->list, 'the card sits in the column of its first step');
        $this->assertNull($b->progress, 'a project without stages has no progress bar');
    }

    public function test_inbox_later_and_done_steps_are_not_next(): void
    {
        $p = $this->project();
        $this->step('Roh', ['project_id' => $p->id, 'list' => 'inbox']);
        $this->step('Irgendwann', ['project_id' => $p->id, 'list' => 'later']);
        $this->step('Fertig', ['project_id' => $p->id, 'is_completed' => true]);
        $this->step('Jetzt', ['project_id' => $p->id]);

        $this->assertSame(['Jetzt'], $this->titles($this->bundle('project', $p->id)));
    }

    public function test_steps_of_a_later_stage_wait_until_the_current_stage_is_done(): void
    {
        $p = $this->project();
        $bugs = $this->stage($p, 'Bugs fixen', 0);
        $open = $this->stage($p, 'Server öffnen', 1);
        $this->step('Backup', ['project_id' => $p->id, 'stage_id' => $open->id, 'sort_order' => 0]);
        $spear = $this->step('Spear Swap', ['project_id' => $p->id, 'stage_id' => $bugs->id, 'sort_order' => 5]);
        $this->step('Alt', ['project_id' => $p->id, 'stage_id' => $bugs->id, 'is_completed' => true]);
        $this->step('Lose Idee', ['project_id' => $p->id, 'sort_order' => 0]);

        $b = $this->bundle('project', $p->id);
        $this->assertSame(['Spear Swap', 'Lose Idee'], $this->titles($b), 'current stage first, then steps in no stage; never a later stage');
        $this->assertSame($bugs->id, $b->stage?->id);
        $this->assertSame([1, 2], [$b->stageNumber, $b->stageCount]);
        $this->assertSame(['done' => 1, 'total' => 2], $b->progress, 'progress counts the current stage only');

        $spear->update(['is_completed' => true]);

        $b = $this->bundle('project', $p->id);
        $this->assertSame(['Backup', 'Lose Idee'], $this->titles($b));
        $this->assertSame($open->id, $b->stage?->id);
        $this->assertSame(['done' => 0, 'total' => 1], $b->progress);
    }

    public function test_an_inbox_step_keeps_its_stage_open(): void
    {
        $p = $this->project();
        $one = $this->stage($p, 'Eins', 0);
        $two = $this->stage($p, 'Zwei', 1);
        $this->step('Noch nicht sortiert', ['project_id' => $p->id, 'stage_id' => $one->id, 'list' => 'inbox']);
        $this->step('Später dran', ['project_id' => $p->id, 'stage_id' => $two->id]);

        $b = $this->bundle('project', $p->id);
        $this->assertSame([], $this->titles($b));
        $this->assertSame($one->id, $b->stage?->id);
        $this->assertNull($b->list, 'nothing to do now: no card on the board');
    }

    public function test_a_step_waiting_on_an_open_task_is_skipped(): void
    {
        $p = $this->project();
        $first = $this->step('Spear Swap', ['project_id' => $p->id, 'sort_order' => 0]);
        $this->step('Rüstung', ['project_id' => $p->id, 'sort_order' => 1, 'waits_on_id' => $first->id]);
        $this->step('Bubble Lift', ['project_id' => $p->id, 'sort_order' => 2]);

        $this->assertSame(['Spear Swap', 'Bubble Lift'], $this->titles($this->bundle('project', $p->id)));

        $first->update(['is_completed' => true]);

        $this->assertSame(['Rüstung', 'Bubble Lift'], $this->titles($this->bundle('project', $p->id)));
    }

    public function test_hidden_ids_are_the_open_work_steps_that_are_not_visible(): void
    {
        $p = $this->project();
        $a = $this->step('A', ['project_id' => $p->id, 'sort_order' => 0]);
        $b = $this->step('B', ['project_id' => $p->id, 'sort_order' => 1]);
        $c = $this->step('C', ['project_id' => $p->id, 'sort_order' => 2]);
        $this->step('Roh', ['project_id' => $p->id, 'list' => 'inbox']);
        $loose = $this->step('Lose');

        $hidden = app(NextSteps::class)->hiddenTaskIds($this->user);

        $this->assertSame([$c->id], $hidden);
        $this->assertNotContains($a->id, $hidden);
        $this->assertNotContains($b->id, $hidden);
        $this->assertNotContains($loose->id, $hidden);
    }

    public function test_a_group_shows_its_next_two_and_progress_over_the_whole_group(): void
    {
        $g = new TaskGroup(['name' => 'Notion för Vocj', 'list' => 'tasks']);
        $g->user_id = $this->user->id;
        $g->save();
        $this->step('Done 1', ['group_id' => $g->id, 'is_completed' => true]);
        $this->step('Done 2', ['group_id' => $g->id, 'is_completed' => true]);
        $this->step('CLAUDE.md Regel', ['group_id' => $g->id, 'sort_order' => 0]);
        $this->step('Notion füllen', ['group_id' => $g->id, 'sort_order' => 1]);
        $this->step('CLAUDE.md Niels', ['group_id' => $g->id, 'sort_order' => 2]);

        $b = $this->bundle('group', $g->id);

        $this->assertSame(['CLAUDE.md Regel', 'Notion füllen'], $this->titles($b));
        $this->assertSame(1, $b->more);
        $this->assertSame(['done' => 2, 'total' => 5], $b->progress);
        $this->assertSame('tasks', $b->list);
    }

    public function test_a_group_that_became_a_project_is_not_a_group_any_more(): void
    {
        $p = $this->project('Zimmer');
        $g = new TaskGroup(['name' => 'Zimmer', 'list' => 'tasks']);
        $g->user_id = $this->user->id;
        $g->project_id = $p->id;
        $g->save();
        $this->step('Kartons', ['group_id' => $g->id, 'project_id' => $p->id]);

        $bundles = app(NextSteps::class)->bundles($this->user);

        $this->assertNull($bundles->first(fn (Bundle $b) => $b->kind === 'group'));
        $this->assertSame(['Kartons'], $this->titles($this->bundle('project', $p->id)));
    }

    public function test_other_users_bundles_are_never_read(): void
    {
        $other = User::factory()->create();
        $p = new Project(['name' => 'Fremd']);
        $p->user_id = $other->id;
        $p->save();
        Task::forceCreate(['user_id' => $other->id, 'title' => 'X', 'list' => 'tasks', 'project_id' => $p->id]);

        $this->assertCount(0, app(NextSteps::class)->bundles($this->user));
    }
}
