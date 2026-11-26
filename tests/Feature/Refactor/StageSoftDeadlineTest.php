<?php

namespace Tests\Feature\Refactor;

use App\Models\EventCategory;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\Task;
use App\Models\User;
use App\Planning\Size;
use App\Services\Planning\PlanningContext;
use App\Services\Planning\Suggestions;
use App\Services\Planning\WeekPlanEditor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Step d-stage-soft (A142, A143): the target date of a project stage counts as the soft deadline of
 * the stage's open steps in the planning engine. Derived on every read: the step keeps its own dates,
 * the earlier date wins, nothing is written to the task.
 * Monday 9 November 2026, 20:00 Zurich, inside a pomodoro block.
 */
class StageSoftDeadlineTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-09 20:00', 'Europe/Zurich'));
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $deep = EventCategory::factory()->for($this->user)->pomodoro()->create(['name' => 'Deep work']);
        (new WeekPlanEditor)->addRepeating($this->user, [1, 2, 3, 4, 5], '19:30', '21:30', $deep->id);

        $this->project = new Project(['name' => 'Enderdragon']);
        $this->project->user_id = $this->user->id;
        $this->project->save();
    }

    private function stage(string $name, ?string $date, int $order = 0): ProjectStage
    {
        return $this->project->stages()->create(['name' => $name, 'target_date' => $date, 'sort_order' => $order]);
    }

    private function step(string $title, ?ProjectStage $stage, array $attrs = []): Task
    {
        return Task::forceCreate(['user_id' => $this->user->id, 'title' => $title, 'list' => 'tasks', 'size' => Size::Quick, 'project_id' => $this->project->id, 'stage_id' => $stage?->id, 'sort_order' => 0, ...$attrs]);
    }

    private function loose(string $title, array $attrs = []): Task
    {
        return Task::forceCreate(['user_id' => $this->user->id, 'title' => $title, 'list' => 'tasks', 'size' => Size::Quick, 'sort_order' => 1, ...$attrs]);
    }

    /** @return array<string, ?string> title => the soft date the engine sees */
    private function plannedSoft(): array
    {
        $context = PlanningContext::for($this->user);
        $planned = $context->plannedTasks();

        return $context->tasks->values()->mapWithKeys(fn (Task $t, int $i) => [$t->title => $planned[$i]->softDeadline])->all();
    }

    public function test_the_stage_date_is_the_soft_deadline_of_its_steps_for_the_engine(): void
    {
        $stage = $this->stage('Bauen', '2026-11-12');
        $this->step('Mauern', $stage);

        $this->assertSame('2026-11-12', $this->plannedSoft()['Mauern']);
    }

    public function test_the_steps_own_earlier_soft_date_wins_and_the_stage_date_wins_when_it_is_earlier(): void
    {
        $stage = $this->stage('Bauen', '2026-11-12');
        $this->step('Eigene Frist früher', $stage, ['soft_deadline' => '2026-11-10']);
        $this->step('Eigene Frist später', $stage, ['soft_deadline' => '2026-11-20']);

        $planned = $this->plannedSoft();
        $this->assertSame('2026-11-10', $planned['Eigene Frist früher']);
        $this->assertSame('2026-11-12', $planned['Eigene Frist später']);
    }

    public function test_nothing_is_written_to_the_task_and_the_hard_deadline_is_left_alone(): void
    {
        $stage = $this->stage('Bauen', '2026-11-12');
        $step = $this->step('Mauern', $stage, ['hard_deadline' => '2026-11-30']);

        $this->plannedSoft();

        $step->refresh();
        $this->assertNull($step->soft_deadline);
        $this->assertSame('2026-11-30', $step->hard_deadline->toDateString());
        $this->assertSame('2026-11-30', PlanningContext::for($this->user)->plannedTasks()[0]->hardDeadline);
        $this->assertNull($step->softDeadline(), 'the task itself still has no soft date: it is derived, not copied');
    }

    public function test_moving_or_clearing_the_stage_date_moves_the_steps_with_it(): void
    {
        $stage = $this->stage('Bauen', '2026-11-12');
        $this->step('Mauern', $stage);

        $stage->update(['target_date' => '2026-11-15']);
        $this->assertSame('2026-11-15', $this->plannedSoft()['Mauern']);

        $stage->update(['target_date' => null]);
        $this->assertNull($this->plannedSoft()['Mauern']);
    }

    public function test_a_stage_without_a_date_and_a_step_without_a_stage_stay_as_they_were(): void
    {
        $this->step('Ohne Datum', $this->stage('Bauen', null));
        $this->step('Ohne Etappe', null);
        $this->loose('Lose', ['soft_deadline' => '2026-11-14']);

        $planned = $this->plannedSoft();
        $this->assertNull($planned['Ohne Datum']);
        $this->assertNull($planned['Ohne Etappe']);
        $this->assertSame('2026-11-14', $planned['Lose']);
    }

    public function test_steps_of_a_later_stage_carry_their_own_stage_date_into_the_forecast(): void
    {
        $this->step('Jetzt', $this->stage('Eins', '2026-11-11', 0));
        $this->step('Später', $this->stage('Zwei', '2026-11-25', 1));

        $planned = $this->plannedSoft();
        $this->assertSame('2026-11-11', $planned['Jetzt']);
        $this->assertSame('2026-11-25', $planned['Später']);
    }

    public function test_a_done_step_is_not_planned_at_all(): void
    {
        $stage = $this->stage('Bauen', '2026-11-12');
        $this->step('Fertig', $stage, ['is_completed' => true, 'completed_at' => now()]);

        $this->assertArrayNotHasKey('Fertig', $this->plannedSoft());
    }

    public function test_the_pick_prefers_a_step_whose_stage_date_is_near_over_a_later_soft_task(): void
    {
        $this->loose('Lose Aufgabe', ['soft_deadline' => '2026-11-16']);
        $this->step('Etappenschritt', $this->stage('Bauen', '2026-11-11'));

        $this->assertSame('Etappenschritt', (new Suggestions)->current($this->user)->task?->title);
    }

    public function test_without_the_stage_date_the_same_step_does_not_jump_ahead(): void
    {
        $this->loose('Lose Aufgabe', ['soft_deadline' => '2026-11-16']);
        $this->step('Etappenschritt', $this->stage('Bauen', null));

        $this->assertSame('Lose Aufgabe', (new Suggestions)->current($this->user)->task?->title);
    }

    public function test_the_steps_own_date_still_beats_a_far_stage_date_in_the_pick(): void
    {
        $this->loose('Lose Aufgabe', ['soft_deadline' => '2026-11-13']);
        $this->step('Etappenschritt', $this->stage('Bauen', '2026-11-30'), ['soft_deadline' => '2026-11-18']);

        $this->assertSame('Lose Aufgabe', (new Suggestions)->current($this->user)->task?->title);
    }
}
