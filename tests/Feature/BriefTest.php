<?php

namespace Tests\Feature;

use App\Livewire\Brief;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class BriefTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $this->actingAs($user);

        return $user;
    }

    public function test_the_brief_renders_on_step_one(): void
    {
        $this->actingUser();

        Livewire::test(Brief::class)
            ->assertOk()
            ->assertSet('step', 1)
            ->assertSee('planen');
    }

    public function test_evening_brief_targets_tomorrow(): void
    {
        $this->actingUser(['brief_when' => 'evening']);

        Livewire::test(Brief::class)
            ->assertSet('targetDate', Carbon::tomorrow()->toDateString());
    }

    public function test_step_navigation_clamps(): void
    {
        $this->actingUser();

        Livewire::test(Brief::class)
            ->call('prevStep')->assertSet('step', 1)
            ->call('nextStep')->assertSet('step', 2)
            ->call('nextStep')->assertSet('step', 3)
            ->call('nextStep')->assertSet('step', 3);
    }

    public function test_it_suggests_important_and_urgent_items(): void
    {
        $user = $this->actingUser();
        $important = Task::factory()->for($user)->tasks()->important()->create();
        $relaxed = Task::factory()->for($user)->tasks()->create(['is_important' => false]);
        $urgentTodo = Task::factory()->for($user)->todos()->create(['deadline' => Carbon::tomorrow()->toDateString()]);

        $component = Livewire::test(Brief::class);

        $this->assertArrayHasKey($important->id, $component->get('taskSessions'));
        $this->assertArrayNotHasKey($relaxed->id, $component->get('taskSessions'));
        $this->assertContains($urgentTodo->id, $component->get('selectedTodos'));
    }

    public function test_free_blocks_merge_when_they_overlap(): void
    {
        $this->actingUser();

        Livewire::test(Brief::class)
            ->call('addFreeBlock', 600, 720)
            ->call('addFreeBlock', 700, 800)
            ->assertCount('freeBlocks', 1)
            ->tap(fn ($c) => $this->assertSame(
                [['start' => 600, 'end' => 800]],
                $c->get('freeBlocks'),
            ));
    }

    public function test_too_short_a_block_is_rejected(): void
    {
        $this->actingUser(); // default work length 25'

        Livewire::test(Brief::class)
            ->call('addFreeBlock', 600, 615) // 15' < 25'
            ->assertCount('freeBlocks', 0);
    }

    public function test_demand_exceeds_capacity_shows_a_soft_warning_without_the_continue_clause(): void
    {
        $user = $this->actingUser();
        $a = Task::factory()->for($user)->tasks()->create();
        $b = Task::factory()->for($user)->tasks()->create();

        Livewire::test(Brief::class)
            ->set('freeBlocks', [['start' => 600, 'end' => 625]]) // room for ~1 session
            ->set('taskSessions', [$a->id => 1, $b->id => 1])     // demand 2
            ->set('step', 2)
            ->assertSee('mehr gewünscht als geplant')
            ->assertDontSee('trotzdem fortfahren');
    }

    public function test_finalize_writes_the_plan_and_marks_the_chosen_items(): void
    {
        $user = $this->actingUser(['brief_when' => 'evening']);
        $target = Carbon::tomorrow()->toDateString();
        $task = Task::factory()->for($user)->tasks()->create();
        $todo = Task::factory()->for($user)->todos()->create();

        Livewire::test(Brief::class)
            ->set('freeBlocks', [['start' => 600, 'end' => 720]]) // 10–12
            ->set('taskSessions', [$task->id => 2])
            ->set('selectedTodos', [$todo->id])
            ->call('finalize')
            ->assertRedirect(route('schedule'));

        $this->assertSame(2, ScheduleEvent::forUser($user)->where('source', 'brief')->where('type', ScheduleEvent::TYPE_WORK)->count());
        $this->assertSame(1, ScheduleEvent::forUser($user)->where('source', 'brief')->where('type', ScheduleEvent::TYPE_TODO)->count());

        $task->refresh();
        $this->assertSame($target, $task->planned_for->toDateString());
        $this->assertSame(2, $task->estimated_sessions);
        $this->assertSame($target, $todo->refresh()->planned_for->toDateString());
        $this->assertTrue($user->refresh()->brief_dismissed_on->isToday());
    }

    public function test_re_running_the_brief_replaces_the_previous_plan(): void
    {
        $user = $this->actingUser();
        $task = Task::factory()->for($user)->tasks()->create();

        $run = fn () => Livewire::test(Brief::class)
            ->set('freeBlocks', [['start' => 600, 'end' => 720]])
            ->set('taskSessions', [$task->id => 1])
            ->set('selectedTodos', [])
            ->call('finalize');

        $run();
        $run();

        // Second run replaced the first — exactly one work session, not two.
        $this->assertSame(1, ScheduleEvent::forUser($user)->where('source', 'brief')->where('type', ScheduleEvent::TYPE_WORK)->count());
    }

    public function test_a_user_cannot_select_another_users_task(): void
    {
        $this->actingUser();
        $foreign = Task::factory()->for(User::factory())->tasks()->create();

        Livewire::test(Brief::class)
            ->call('toggleTask', $foreign->id)
            ->assertCount('taskSessions', 0); // find() scoped to owner → ignored
    }
}
