<?php

namespace Tests\Feature\Refactor;

use App\Livewire\Shell\Board;
use App\Models\EventCategory;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Planning\Size;
use App\Services\FocusSession;
use App\Services\Planning\Capacity;
use App\Services\Planning\Suggestions;
use App\Services\Planning\WeekPlanEditor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The daily To-do session (Niels, 2026-11-24): the loose To-dos are bundled into one 25 minute
 * session, an unsized To-do counts as Quick, the board shows one card, and inside a session the
 * next pick stays a To-do.
 */
class TodoSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Monday 9 November 2026, 20:00 Zurich, inside a pomodoro block.
        $this->travelTo(CarbonImmutable::parse('2026-11-09 20:00', 'Europe/Zurich'));
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $deep = EventCategory::factory()->for($this->user)->pomodoro()->create(['name' => 'Deep work']);
        (new WeekPlanEditor)->addRepeating($this->user, [1, 2, 3, 4, 5], '19:30', '21:30', $deep->id);
    }

    private function task(string $title, string $list = 'todos', array $attrs = []): Task
    {
        return Task::forceCreate(array_merge(['user_id' => $this->user->id, 'title' => $title, 'list' => $list, 'size' => Size::Quick], $attrs));
    }

    private function titles(): array
    {
        return app(Suggestions::class)->todoSession($this->user)->tasks->pluck('title')->all();
    }

    public function test_the_session_is_filled_up_to_25_minutes_and_the_rest_waits(): void
    {
        $this->task('A', attrs: ['sort_order' => 0]);
        $this->task('B', attrs: ['sort_order' => 1]);
        $this->task('C', attrs: ['sort_order' => 2]);

        $session = app(Suggestions::class)->todoSession($this->user);

        $this->assertSame(['A', 'B'], $session->tasks->pluck('title')->all());
        $this->assertSame(20, $session->minutes());
        $this->assertSame(1, $session->waiting);
    }

    public function test_deadline_work_comes_first_then_wichtig_then_the_board_order(): void
    {
        $this->task('Plain', attrs: ['sort_order' => 0]);
        $this->task('Marked', attrs: ['sort_order' => 1, 'is_important' => true]);
        $this->task('Dated', attrs: ['sort_order' => 2, 'soft_deadline' => '2026-11-20']);

        $this->assertSame(['Dated', 'Marked'], $this->titles());
    }

    public function test_a_todo_without_size_counts_as_quick_and_a_task_as_short(): void
    {
        $todo = $this->task('Klein', attrs: ['size' => null]);
        $task = $this->task('Gross', 'tasks', ['size' => null]);

        $this->assertSame(Size::Quick, $todo->effectiveSize());
        $this->assertSame(Size::Short, $task->effectiveSize());
        $this->assertSame(10, $todo->toPlannedTask()->remainingMinutes());
        $this->assertSame(25, $task->toPlannedTask()->remainingMinutes());
    }

    public function test_eight_unsized_todos_load_the_forecast_with_80_minutes(): void
    {
        foreach (range(1, 8) as $i) {
            $this->task("To-do $i", attrs: ['size' => null, 'hard_deadline' => '2026-11-12']);
        }

        $load = collect(app(Capacity::class)->forecast($this->user)->days)->sum(fn ($d) => $d->loadMinutes);

        $this->assertSame(80, $load);
    }

    public function test_tasks_later_and_project_steps_are_not_part_of_it(): void
    {
        $project = new Project(['name' => 'P']);
        $project->user_id = $this->user->id;
        $project->save();

        $this->task('Task', 'tasks');
        $this->task('Idee', 'later');
        $this->task('Inbox', 'inbox');
        $this->task('Schritt', attrs: ['project_id' => $project->id]);
        $this->task('Lose', attrs: ['sort_order' => 5]);

        $this->assertSame(['Lose'], $this->titles());
    }

    public function test_after_a_todo_the_next_pick_stays_a_todo_but_after_a_task_it_does_not(): void
    {
        $first = $this->task('Erstes', attrs: ['sort_order' => 1]);
        $second = $this->task('Zweites', attrs: ['sort_order' => 2]);
        $urgent = $this->task('Dringende Task', 'tasks', ['hard_deadline' => '2026-11-10']);
        $suggestions = app(Suggestions::class);

        $this->assertSame($second->id, $suggestions->nextInSession($this->user, $first->id));
        // Without a To-do left, the normal ranking takes over again.
        $second->update(['is_completed' => true, 'completed_at' => now()]);
        $this->assertSame($urgent->id, $suggestions->nextInSession($this->user, $first->id));
        $this->assertSame($first->id, $suggestions->nextInSession($this->user, $urgent->id));
    }

    public function test_okay_ties_end_on_the_board_order_before_the_size(): void
    {
        $big = $this->task('Gross', 'tasks', ['size' => Size::Short, 'sort_order' => 0]);
        $this->task('Klein', 'tasks', ['size' => Size::Quick, 'sort_order' => 1]);

        $this->assertSame($big->id, app(Suggestions::class)->current($this->user)->task->id);
    }

    public function test_the_board_shows_one_card_with_the_session_and_what_waits(): void
    {
        $this->task('A', attrs: ['sort_order' => 0]);
        $this->task('B', attrs: ['sort_order' => 1]);
        $this->task('C', attrs: ['sort_order' => 2]);

        $board = Livewire::actingAs($this->user)->test(Board::class)
            ->assertSeeHtml('data-todo-session')
            ->assertSee('To-do-Session')
            ->assertSee('2 · 20 min')
            ->assertSee('+ 1 To-do wartet auf die nächste Session');

        $this->assertSame(['C'], $board->instance()->lists['todos']->pluck('title')->all(), 'the session To-dos sit in the card, not twice');
        $this->assertSame(3, $board->instance()->counts['todos']);
    }

    public function test_starting_the_card_starts_its_first_todo(): void
    {
        $a = $this->task('A', attrs: ['sort_order' => 0]);
        $this->task('B', attrs: ['sort_order' => 1]);

        Livewire::actingAs($this->user)->test(Board::class)->call('start', $a->id);
        $this->assertSame($a->id, app(FocusSession::class)->current($this->user)->taskId);
    }

    public function test_no_todos_no_card(): void
    {
        $this->task('Nur eine Task', 'tasks');

        Livewire::actingAs($this->user)->test(Board::class)->assertDontSeeHtml('data-todo-session');
    }
}
