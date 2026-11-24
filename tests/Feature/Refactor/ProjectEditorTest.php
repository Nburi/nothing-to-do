<?php

namespace Tests\Feature\Refactor;

use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Task;
use App\Models\User;
use App\Services\Projects\ProjectEditor;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    /** @return list<string> the open steps of a stage (null = no stage) in their order */
    private function titles(Project $project, ?ProjectStage $stage = null): array
    {
        return Task::where('project_id', $project->id)->active()
            ->when($stage === null, fn ($q) => $q->whereNull('stage_id'), fn ($q) => $q->where('stage_id', $stage->id))
            ->orderBy('sort_order')->orderBy('id')->pluck('title')->all();
    }

    // ---- 4.3c: place() is the one move behind dragging, the keyboard and the ⋯ menu ---------------

    public function test_place_puts_a_step_at_a_position_and_numbers_the_list_again(): void
    {
        $p = $this->editor->createProject($this->user, 'P');
        [$a, $b, $c] = array_map(fn ($t) => $this->editor->addProjectStep($this->user, $p, null, $t), ['A', 'B', 'C']);

        $move = $this->editor->place($c, null, 0);

        $this->assertTrue($move->changed);
        $this->assertSame([2, 0], [$move->fromIndex, $move->toIndex]);
        $this->assertSame(['C', 'A', 'B'], $this->titles($p));
        $this->assertSame([0, 1, 2], Task::where('project_id', $p->id)->orderBy('sort_order')->pluck('sort_order')->all(), 'stable positions: 0..n-1');

        $this->editor->place($c->fresh(), null, 1);
        $this->assertSame(['A', 'C', 'B'], $this->titles($p));
    }

    public function test_place_without_a_position_goes_to_the_end_and_a_position_past_the_end_is_the_end(): void
    {
        $p = $this->editor->createProject($this->user, 'P');
        [$a, $b, $c] = array_map(fn ($t) => $this->editor->addProjectStep($this->user, $p, null, $t), ['A', 'B', 'C']);

        $this->editor->place($a, null);
        $this->assertSame(['B', 'C', 'A'], $this->titles($p));

        $this->editor->place($b->fresh(), null, 99);
        $this->assertSame(['C', 'A', 'B'], $this->titles($p));

        $this->editor->place($c->fresh(), null, -5); // below the start is the start
        $this->assertSame(['C', 'A', 'B'], $this->titles($p));
    }

    public function test_place_is_idempotent_a_step_that_sits_there_is_left_alone(): void
    {
        $p = $this->editor->createProject($this->user, 'P');
        $one = $this->editor->addStage($p, 'Eins', null);
        [$a, $b, $c] = array_map(fn ($t) => $this->editor->addProjectStep($this->user, $p, $one, $t), ['A', 'B', 'C']);

        $first = $this->editor->place($c, $one, 0);
        $again = $this->editor->place($c->fresh(), $one, 0);
        $samePlace = $this->editor->place($b->fresh(), $one, 2);

        $this->assertTrue($first->changed);
        $this->assertFalse($again->changed, 'the same call twice moves nothing the second time');
        $this->assertFalse($samePlace->changed, 'B is already third');
        $this->assertSame(['C', 'A', 'B'], $this->titles($p, $one));
    }

    public function test_place_moves_a_step_into_another_stage_at_a_position(): void
    {
        $p = $this->editor->createProject($this->user, 'P');
        $one = $this->editor->addStage($p, 'Eins', null);
        $two = $this->editor->addStage($p, 'Zwei', null);
        [$a, $b] = array_map(fn ($t) => $this->editor->addProjectStep($this->user, $p, $one, $t), ['A', 'B']);
        [$x, $y] = array_map(fn ($t) => $this->editor->addProjectStep($this->user, $p, $two, $t), ['X', 'Y']);

        $move = $this->editor->place($a, $two, 1);

        $this->assertSame([$one->id, 0, $two->id, 1, 2], [$move->fromStageId, $move->fromIndex, $move->toStageId, $move->toIndex, $move->toStageNumber]);
        $this->assertTrue($move->stageChanged());
        $this->assertSame(['X', 'A', 'Y'], $this->titles($p, $two));
        $this->assertSame(['B'], $this->titles($p, $one));
        $this->assertSame($two->id, $a->fresh()->stage_id);
    }

    public function test_place_into_no_stage_and_back_again(): void
    {
        $p = $this->editor->createProject($this->user, 'P');
        $one = $this->editor->addStage($p, 'Eins', null);
        $a = $this->editor->addProjectStep($this->user, $p, $one, 'A');
        $this->editor->addProjectStep($this->user, $p, null, 'Lose');

        $out = $this->editor->place($a, null, 0);
        $this->assertNull($out->toStageNumber);
        $this->assertSame(['A', 'Lose'], $this->titles($p));
        $this->assertNull($a->fresh()->stage_id);

        $this->editor->place($a->fresh(), $one);
        $this->assertSame(['A'], $this->titles($p, $one));
        $this->assertSame(['Lose'], $this->titles($p));
    }

    public function test_a_stage_of_another_project_is_refused(): void
    {
        $p = $this->editor->createProject($this->user, 'P');
        $q = $this->editor->createProject($this->user, 'Q');
        $foreign = $this->editor->addStage($q, 'Fremd', null);
        $step = $this->editor->addProjectStep($this->user, $p, null, 'S');

        try {
            $this->editor->place($step, $foreign, 0);
            $this->fail('a stage of another project must be refused');
        } catch (InvalidArgumentException) {
            $this->assertNull($step->fresh()->stage_id);
        }

        $this->expectException(InvalidArgumentException::class);
        $this->editor->putBack($step, $foreign, 0, []);
    }

    public function test_place_leaves_other_projects_and_other_users_alone(): void
    {
        $p = $this->editor->createProject($this->user, 'P');
        $q = $this->editor->createProject($this->user, 'Q');
        [$a, $b] = array_map(fn ($t) => $this->editor->addProjectStep($this->user, $p, null, $t), ['A', 'B']);
        $other = $this->editor->addProjectStep($this->user, $q, null, 'Andere');
        $stranger = User::factory()->create();
        $theirs = $this->editor->addProjectStep($stranger, $this->editor->createProject($stranger, 'Ihr'), null, 'Ihr Schritt');

        $this->editor->place($b, null, 0);

        $this->assertSame([1, 1], [$other->fresh()->sort_order, $theirs->fresh()->sort_order]);
        $this->assertSame(['B', 'A'], $this->titles($p));
    }

    public function test_place_of_a_finished_step_is_not_found(): void
    {
        $p = $this->editor->createProject($this->user, 'P');
        $step = $this->editor->addProjectStep($this->user, $p, null, 'S');
        $step->forceFill(['is_completed' => true])->save();

        $this->expectException(ModelNotFoundException::class);
        $this->editor->place($step, null, 0);
    }

    public function test_place_reorders_the_steps_of_a_group_and_a_group_has_no_stages(): void
    {
        $g = $this->editor->createGroup($this->user, 'Notion', 'tasks');
        [$a, $b, $c] = array_map(fn ($t) => $this->editor->addGroupStep($this->user, $g, $t), ['A', 'B', 'C']);
        $someStage = $this->editor->addStage($this->editor->createProject($this->user, 'P'), 'Etappe', null);

        $this->editor->place($c, null, 0);
        $this->assertSame(['C', 'A', 'B'], Task::where('group_id', $g->id)->orderBy('sort_order')->pluck('title')->all());

        $this->expectException(InvalidArgumentException::class);
        $this->editor->place($a, $someStage, 0);
    }

    public function test_two_quick_moves_each_see_the_result_of_the_other(): void
    {
        $p = $this->editor->createProject($this->user, 'P');
        [$a, $b, $c] = array_map(fn ($t) => $this->editor->addProjectStep($this->user, $p, null, $t), ['A', 'B', 'C']);

        // Both calls were prepared from the page as it was drawn (A, B, C); the second runs on the first's result.
        $this->editor->place($c, null, 0);
        $this->editor->place($b, null, 0);

        $this->assertSame(['B', 'C', 'A'], $this->titles($p));
        $this->assertSame([0, 1, 2], Task::where('project_id', $p->id)->orderBy('sort_order')->pluck('sort_order')->all());
    }

    // ---- 4.3c: a move that contradicts a "wartet auf" lets go of it, and "Rückgängig" brings it back ----

    /** A project with stages one and two; A, B in one; X, Y in two. */
    private function twoStages(): array
    {
        $p = $this->editor->createProject($this->user, 'P');
        $one = $this->editor->addStage($p, 'Eins', null);
        $two = $this->editor->addStage($p, 'Zwei', null);
        [$a, $b] = array_map(fn ($t) => $this->editor->addProjectStep($this->user, $p, $one, $t), ['A', 'B']);
        [$x, $y] = array_map(fn ($t) => $this->editor->addProjectStep($this->user, $p, $two, $t), ['X', 'Y']);

        return [$p, $one, $two, $a, $b, $x, $y];
    }

    public function test_a_blocker_moved_to_a_later_stage_frees_the_step_that_waited_in_an_earlier_one(): void
    {
        [$p, $one, $two, $a, $b] = $this->twoStages();
        $this->editor->setWaitsOn($a, $b); // both in stage one: fine

        $move = $this->editor->place($b, $two, 0);

        $this->assertSame([$a->id => $b->id], $move->cleared, 'A would wait for something that cannot start before A is done');
        $this->assertNull($a->fresh()->waits_on_id);
    }

    public function test_a_waiting_step_moved_into_an_earlier_stage_than_its_blocker_is_freed(): void
    {
        [$p, $one, $two, $a, $b, $x, $y] = $this->twoStages();
        $this->editor->setWaitsOn($x, $y); // both in stage two: fine

        $move = $this->editor->place($x, $one, 0);

        $this->assertSame([$x->id => $y->id], $move->cleared);
        $this->assertNull($x->fresh()->waits_on_id);
    }

    public function test_a_link_stays_when_the_order_still_holds_or_a_step_has_no_stage(): void
    {
        [$p, $one, $two, $a, $b, $x] = $this->twoStages();
        $this->editor->setWaitsOn($x, $a); // X (two) waits on A (one): the right way round

        $this->assertSame([], $this->editor->place($a, $one, 1)->cleared, 'a move inside the stage never contradicts');
        $this->assertSame([], $this->editor->place($x->fresh(), $two, 0)->cleared);
        $this->assertSame([], $this->editor->place($a->fresh(), null, 0)->cleared, 'no stage never contradicts');
        $this->assertSame([], $this->editor->place($x->fresh(), null, 0)->cleared);
        $this->assertSame($a->id, $x->fresh()->waits_on_id);
    }

    public function test_a_finished_blocker_never_contradicts(): void
    {
        [$p, $one, $two, $a, $b] = $this->twoStages();
        $this->editor->setWaitsOn($a, $b);
        $b->forceFill(['is_completed' => true])->save();

        $this->assertSame([], $this->editor->place($a, $two, 0)->cleared);
        $this->assertSame($b->id, $a->fresh()->waits_on_id, 'a finished step is simply not waited on any more; the link is left as it is');
    }

    public function test_put_back_restores_the_place_the_stage_and_the_link_that_was_let_go(): void
    {
        [$p, $one, $two, $a, $b, $x, $y] = $this->twoStages();
        $this->editor->setWaitsOn($a, $b);

        $move = $this->editor->place($b, $two, 1);
        $this->assertSame(['X', 'B', 'Y'], $this->titles($p, $two));
        $this->assertNull($a->fresh()->waits_on_id);

        $this->editor->putBack($b->fresh(), $one, $move->fromIndex, $move->cleared);

        $this->assertSame(['A', 'B'], $this->titles($p, $one));
        $this->assertSame(['X', 'Y'], $this->titles($p, $two));
        $this->assertSame($b->id, $a->fresh()->waits_on_id, 'the waiting is back');
    }

    public function test_put_back_does_not_overwrite_a_link_someone_set_since(): void
    {
        [$p, $one, $two, $a, $b, $x] = $this->twoStages();
        $this->editor->setWaitsOn($a, $b);
        $move = $this->editor->place($b, $two, 0);
        $this->editor->setWaitsOn($a->fresh(), $x); // the user pointed A at X in the meantime (X is in stage two: contradiction by hand, allowed)

        $this->editor->putBack($b->fresh(), $one, $move->fromIndex, $move->cleared);

        $this->assertSame($x->id, $a->fresh()->waits_on_id);
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
