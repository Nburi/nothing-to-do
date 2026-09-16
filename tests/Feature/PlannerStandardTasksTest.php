<?php

namespace Tests\Feature;

use App\Livewire\Planner;
use App\Models\AgendaEntry;
use App\Models\Task;
use App\Models\TaskDayPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlannerStandardTasksTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::factory()->create(['planner_enabled' => true]);
        $this->actingAs($user);

        return $user;
    }

    public function test_opening_todos_clear_defaults_to_today_and_the_default_duration(): void
    {
        $this->actingUser();

        $component = Livewire::test(Planner::class)->call('openStandardTask', 'todos_clear');

        $this->assertSame('todos_clear', $component->get('standardTemplate'));
        $this->assertSame(now()->toDateString(), $component->get('standardDate'));
        $this->assertSame(30, $component->get('standardDuration'));
    }

    public function test_opening_an_unknown_template_key_is_ignored(): void
    {
        $this->actingUser();

        Livewire::test(Planner::class)
            ->call('openStandardTask', 'not-a-real-template')
            ->assertSet('standardTemplate', null);
    }

    public function test_saving_todos_clear_creates_a_todo_with_the_chosen_duration_on_the_chosen_day(): void
    {
        $this->actingUser();
        $date = now()->addDays(2)->toDateString();

        Livewire::test(Planner::class)
            ->call('openStandardTask', 'todos_clear')
            ->set('standardDate', $date)
            ->set('standardDuration', 45)
            ->call('saveStandardTask')
            ->assertHasNoErrors()
            ->assertSet('standardTemplate', null);

        $task = Task::sole();
        $this->assertSame('ToDos erledigen', $task->title);
        $this->assertSame('todos', $task->list);
        $this->assertSame(45, $task->duration_minutes);

        $plan = TaskDayPlan::sole();
        $this->assertSame($task->id, $plan->task_id);
        $this->assertSame($date, $plan->planned_date->toDateString());
    }

    public function test_todos_clear_duration_must_stay_within_bounds(): void
    {
        $this->actingUser();

        Livewire::test(Planner::class)
            ->call('openStandardTask', 'todos_clear')
            ->set('standardDuration', 9999)
            ->call('saveStandardTask')
            ->assertHasErrors(['standardDuration']);

        $this->assertSame(0, Task::count());
    }

    public function test_saving_study_general_creates_a_plain_lernen_task(): void
    {
        $this->actingUser();

        Livewire::test(Planner::class)
            ->call('openStandardTask', 'study')
            ->call('saveStandardTask')
            ->assertHasNoErrors();

        $task = Task::sole();
        $this->assertSame('Lernen', $task->title);
        $this->assertSame('tasks', $task->list);
        $this->assertNull($task->deadline);
    }

    public function test_study_subject_mode_requires_a_subject(): void
    {
        $this->actingUser();

        Livewire::test(Planner::class)
            ->call('openStandardTask', 'study')
            ->call('setStandardStudyMode', 'subject')
            ->call('saveStandardTask')
            ->assertHasErrors(['standardStudySubject']);

        $this->assertSame(0, Task::count());
    }

    public function test_saving_study_subject_mode_builds_the_expected_title(): void
    {
        $this->actingUser();

        Livewire::test(Planner::class)
            ->call('openStandardTask', 'study')
            ->call('setStandardStudyMode', 'subject')
            ->set('standardStudySubject', 'Mathematik')
            ->call('saveStandardTask')
            ->assertHasNoErrors();

        $this->assertSame('Lernen: Mathematik', Task::sole()->title);
    }

    public function test_picking_an_open_exam_prefills_the_subject_and_stamps_the_deadline_on_save(): void
    {
        $user = $this->actingUser();
        $exam = AgendaEntry::factory()->for($user)->exam()->create(['subject' => 'Französisch', 'date' => now()->addWeek()->toDateString()]);

        $component = Livewire::test(Planner::class)
            ->call('openStandardTask', 'study')
            ->call('setStandardStudyMode', 'exam')
            ->call('pickStandardStudyExam', $exam->id);

        $this->assertSame('Französisch', $component->get('standardStudySubject'));

        $component->call('saveStandardTask')->assertHasNoErrors();

        $task = Task::sole();
        $this->assertSame('Lernen für Prüfung: Französisch', $task->title);
        $this->assertSame($exam->date->toDateString(), $task->deadline->toDateString());
    }

    public function test_picking_a_foreign_exam_entry_is_rejected(): void
    {
        $this->actingUser();
        $stranger = AgendaEntry::factory()->exam()->create();

        Livewire::test(Planner::class)
            ->call('openStandardTask', 'study')
            ->call('setStandardStudyMode', 'exam')
            ->call('pickStandardStudyExam', $stranger->id)
            ->assertSet('standardStudyAgendaEntryId', null)
            ->assertSet('standardStudySubject', '');
    }

    public function test_exam_options_are_empty_while_the_agenda_module_is_hidden(): void
    {
        $user = User::factory()->create(['planner_enabled' => true, 'hidden_modules' => ['agenda']]);
        $this->actingAs($user);
        AgendaEntry::factory()->for($user)->exam()->create();

        $options = Livewire::test(Planner::class)->instance()->standardStudyExamOptions;

        $this->assertCount(0, $options);
    }

    public function test_switching_study_mode_clears_the_previous_modes_input(): void
    {
        $user = $this->actingUser();
        $exam = AgendaEntry::factory()->for($user)->exam()->create();

        Livewire::test(Planner::class)
            ->call('openStandardTask', 'study')
            ->call('setStandardStudyMode', 'exam')
            ->call('pickStandardStudyExam', $exam->id)
            ->call('setStandardStudyMode', 'subject')
            ->assertSet('standardStudyAgendaEntryId', null)
            ->assertSet('standardStudySubject', '');
    }

    public function test_the_chosen_date_must_stay_within_the_planner_horizon(): void
    {
        $this->actingUser();

        Livewire::test(Planner::class)
            ->call('openStandardTask', 'todos_clear')
            ->set('standardDate', now()->subDay()->toDateString())
            ->call('saveStandardTask')
            ->assertHasErrors(['standardDate']);

        Livewire::test(Planner::class)
            ->call('openStandardTask', 'todos_clear')
            ->set('standardDate', now()->addDays(30)->toDateString())
            ->call('saveStandardTask')
            ->assertHasErrors(['standardDate']);

        $this->assertSame(0, Task::count());
    }

    public function test_a_placed_standard_task_shows_up_on_the_board_like_any_other_task(): void
    {
        $this->actingUser();
        $date = now()->toDateString();

        Livewire::test(Planner::class)
            ->call('openStandardTask', 'todos_clear')
            ->set('standardDate', $date)
            ->set('standardDuration', 20)
            ->call('saveStandardTask');

        $component = Livewire::test(Planner::class);
        $day = $component->instance()->board->get($date);

        $this->assertSame(1, $day['tasks']->count());
        $this->assertSame('ToDos erledigen', $day['tasks']->first()['title']);
        $this->assertSame(20, $day['tasks']->first()['duration']);
    }
}
