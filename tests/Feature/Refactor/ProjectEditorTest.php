<?php

namespace Tests\Feature\Refactor;

use App\Models\Task;
use App\Models\User;
use App\Services\Projects\ProjectEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * A45, part one (step 4.3): the writes behind the project and group pages. Nothing deletes a task,
 * order is per stage or group, "wartet auf" never forms a loop.
 */
class ProjectEditorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ProjectEditor $editor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->editor = new ProjectEditor;
    }

    public function test_steps_are_added_at_the_end_of_their_stage(): void
    {
        $p = $this->editor->createProject($this->user, 'Enderdragon');
        $bugs = $this->editor->addStage($p, 'Bugs fixen', '2026-11-30');
        $a = $this->editor->addProjectStep($this->user, $p, $bugs, 'Spear Swap');
        $b = $this->editor->addProjectStep($this->user, $p, $bugs, 'Bubble Lift', 'todos');
        $loose = $this->editor->addProjectStep($this->user, $p, null, 'Idee');

        $this->assertSame([1, 2], [$a->sort_order, $b->sort_order]);
        $this->assertSame(1, $loose->sort_order, 'no stage has its own order');
        $this->assertSame(['tasks', 'todos'], [$a->list, $b->list]);
        $this->assertSame($this->user->id, $a->user_id);
        $this->assertSame('2026-11-30', $bugs->fresh()->target_date->toDateString());
    }

    public function test_move_swaps_with_the_neighbour_and_stops_at_the_ends(): void
    {
        $p = $this->editor->createProject($this->user, 'P');
        [$a, $b, $c] = array_map(fn ($t) => $this->editor->addProjectStep($this->user, $p, null, $t), ['A', 'B', 'C']);

        $this->editor->move($c, -1);
        $this->editor->move($a, -1); // already first: nothing happens

        $order = Task::where('project_id', $p->id)->orderBy('sort_order')->pluck('title')->all();
        $this->assertSame(['A', 'C', 'B'], $order);
    }

    public function test_removing_a_stage_keeps_its_steps(): void
    {
        $p = $this->editor->createProject($this->user, 'P');
        $stage = $this->editor->addStage($p, 'Eins', null);
        $step = $this->editor->addProjectStep($this->user, $p, $stage, 'Bleibt');

        $this->editor->removeStage($stage);

        $this->assertNotNull($step->fresh());
        $this->assertNull($step->fresh()->stage_id);
        $this->assertSame($p->id, $step->fresh()->project_id);
    }

    public function test_set_stage_moves_a_step_to_the_end_of_the_new_stage(): void
    {
        $p = $this->editor->createProject($this->user, 'P');
        $one = $this->editor->addStage($p, 'Eins', null);
        $two = $this->editor->addStage($p, 'Zwei', null);
        $this->editor->addProjectStep($this->user, $p, $two, 'Schon da');
        $step = $this->editor->addProjectStep($this->user, $p, $one, 'Wandert');

        $this->editor->setStage($step, $two);

        $this->assertSame([$two->id, 2], [$step->fresh()->stage_id, $step->fresh()->sort_order]);
    }

    public function test_a_stage_of_another_project_is_refused(): void
    {
        $p = $this->editor->createProject($this->user, 'P');
        $q = $this->editor->createProject($this->user, 'Q');
        $foreign = $this->editor->addStage($q, 'Fremd', null);
        $step = $this->editor->addProjectStep($this->user, $p, null, 'S');

        $this->expectException(InvalidArgumentException::class);
        $this->editor->setStage($step, $foreign);
    }

    public function test_waits_on_refuses_loops_and_other_projects(): void
    {
        $p = $this->editor->createProject($this->user, 'P');
        [$a, $b, $c] = array_map(fn ($t) => $this->editor->addProjectStep($this->user, $p, null, $t), ['A', 'B', 'C']);
        $this->editor->setWaitsOn($b, $a);
        $this->editor->setWaitsOn($c, $b);

        try {
            $this->editor->setWaitsOn($a, $c->fresh());
            $this->fail('A → C → B → A is a loop');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('loop', $e->getMessage());
        }

        $other = $this->editor->addProjectStep($this->user, $this->editor->createProject($this->user, 'Q'), null, 'X');
        $this->expectException(InvalidArgumentException::class);
        $this->editor->setWaitsOn($a, $other);
    }

    public function test_waits_on_null_clears_it(): void
    {
        $p = $this->editor->createProject($this->user, 'P');
        [$a, $b] = array_map(fn ($t) => $this->editor->addProjectStep($this->user, $p, null, $t), ['A', 'B']);
        $this->editor->setWaitsOn($b, $a);
        $this->editor->setWaitsOn($b->fresh(), null);

        $this->assertNull($b->fresh()->waits_on_id);
    }

    public function test_a_group_holds_one_list_and_new_steps_land_in_it(): void
    {
        $g = $this->editor->createGroup($this->user, 'Notion', 'todos');
        $step = $this->editor->addGroupStep($this->user, $g, 'Seite anlegen');

        $this->assertSame(['todos', $g->id, 1], [$step->list, $step->group_id, $step->sort_order]);

        $this->expectException(InvalidArgumentException::class);
        $this->editor->createGroup($this->user, 'Falsch', 'later');
    }
}
