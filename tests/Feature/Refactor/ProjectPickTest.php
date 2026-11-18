<?php

namespace Tests\Feature\Refactor;

use App\Models\EventCategory;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Planning\Size;
use App\Services\Planning\Suggestions;
use App\Services\Planning\WeekPlanEditor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A43 (step 4.3, Niels 2026-11-17 "Vorschlag two"): the suggestion may pick either of the two
 * steps a project shows, never one of the steps that wait on its page. Same inside a session.
 */
class ProjectPickTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        // Monday 9 November 2026, 20:00 Zurich, inside a pomodoro block.
        $this->travelTo(CarbonImmutable::parse('2026-11-09 20:00', 'Europe/Zurich'));
        $this->user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $deep = EventCategory::factory()->for($this->user)->pomodoro()->create(['name' => 'Deep work']);
        (new WeekPlanEditor)->addRepeating($this->user, [1, 2, 3, 4, 5], '19:30', '21:30', $deep->id);

        $this->project = new Project(['name' => 'Enderdragon']);
        $this->project->user_id = $this->user->id;
        $this->project->save();
    }

    private function step(string $title, int $order, array $attrs = []): Task
    {
        return Task::forceCreate(['user_id' => $this->user->id, 'title' => $title, 'list' => 'tasks', 'size' => Size::Quick, 'project_id' => $this->project->id, 'sort_order' => $order, ...$attrs]);
    }

    /** Every task "Another" walks through before it starts over. */
    private function cycle(): array
    {
        $suggestions = new Suggestions;
        $seen = [];
        $current = $suggestions->current($this->user);
        for ($i = 0; $i < 10 && $current->hasPick() && ! in_array($current->task->title, $seen, true); $i++) {
            $seen[] = $current->task->title;
            $current = $suggestions->another($this->user);
        }

        sort($seen);

        return $seen;
    }

    public function test_only_the_two_visible_steps_can_be_suggested(): void
    {
        // The hidden step has the nearest hard deadline: it would win if it were a candidate.
        $this->step('Spear Swap', 0);
        $this->step('Bubble Lift', 1);
        $this->step('Rüstung', 2, ['hard_deadline' => '2026-11-10']);

        $this->assertSame(['Bubble Lift', 'Spear Swap'], $this->cycle());
    }

    public function test_a_loose_task_and_the_visible_steps_compete_as_usual(): void
    {
        Task::forceCreate(['user_id' => $this->user->id, 'title' => 'Mathe', 'list' => 'todos', 'size' => Size::Quick]);
        $this->step('Spear Swap', 0);
        $this->step('Bubble Lift', 1);
        $this->step('Rüstung', 2);

        $this->assertSame(['Bubble Lift', 'Mathe', 'Spear Swap'], $this->cycle());
    }

    public function test_after_done_in_a_session_the_next_task_is_never_a_hidden_step(): void
    {
        $first = $this->step('Spear Swap', 0);
        $this->step('Bubble Lift', 1);
        $this->step('Rüstung', 2, ['hard_deadline' => '2026-11-10']);

        $next = (new Suggestions)->nextInSession($this->user, $first->id);

        $this->assertSame('Bubble Lift', Task::find($next)?->title);
    }
}
