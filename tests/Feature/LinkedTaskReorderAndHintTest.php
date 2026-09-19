<?php

namespace Tests\Feature;

use App\Livewire\Schedule;
use App\Livewire\Settings;
use App\Models\EventCategory;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LinkedTaskReorderAndHintTest extends TestCase
{
    use RefreshDatabase;

    private function pinnedCategory(User $user, int $count = 3): array
    {
        $category = EventCategory::factory()->for($user)->pomodoro()->create(['task_source' => 'tasks']);
        $tasks = [];

        foreach (range(0, $count - 1) as $i) {
            $tasks[$i] = Task::factory()->for($user)->todos()->create(['title' => 'T'.$i]);
            $category->pinnedTasks()->attach($tasks[$i]->id, ['sort_order' => $i]);
        }

        return [$category, $tasks];
    }

    public function test_a_pinned_task_can_be_moved_up_and_down(): void
    {
        $user = User::factory()->create();
        [$category, $t] = $this->pinnedCategory($user);

        Livewire::actingAs($user)->test(Settings::class)
            ->call('movePinnedTask', $category->id, $t[2]->id, 'up');

        $this->assertSame([$t[0]->id, $t[2]->id, $t[1]->id], $category->pinnedTasks()->pluck('tasks.id')->all());

        Livewire::actingAs($user)->test(Settings::class)
            ->call('movePinnedTask', $category->id, $t[0]->id, 'down');

        $this->assertSame([$t[2]->id, $t[0]->id, $t[1]->id], $category->pinnedTasks()->pluck('tasks.id')->all());
    }

    public function test_moving_past_either_end_is_a_no_op(): void
    {
        $user = User::factory()->create();
        [$category, $t] = $this->pinnedCategory($user);

        $component = Livewire::actingAs($user)->test(Settings::class);
        $component->call('movePinnedTask', $category->id, $t[0]->id, 'up');
        $component->call('movePinnedTask', $category->id, $t[2]->id, 'down');
        $component->call('movePinnedTask', $category->id, $t[1]->id, 'sideways');

        $this->assertSame([$t[0]->id, $t[1]->id, $t[2]->id], $category->pinnedTasks()->pluck('tasks.id')->all());
    }

    public function test_a_foreign_category_cannot_be_reordered(): void
    {
        $owner = User::factory()->create();
        [$category, $t] = $this->pinnedCategory($owner);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Livewire::actingAs(User::factory()->create())->test(Settings::class)
            ->call('movePinnedTask', $category->id, $t[2]->id, 'up');
    }

    public function test_an_events_bound_tasks_can_be_reordered_before_saving(): void
    {
        $user = User::factory()->create();
        $a = Task::factory()->for($user)->todos()->create(['title' => 'A']);
        $b = Task::factory()->for($user)->todos()->create(['title' => 'B']);
        $c = Task::factory()->for($user)->todos()->create(['title' => 'C']);

        $component = Livewire::actingAs($user)->test(Schedule::class)
            ->call('toggleEventLinkedTask', $a->id)
            ->call('toggleEventLinkedTask', $b->id)
            ->call('toggleEventLinkedTask', $c->id)
            ->call('moveEventLinkedTask', $c->id, 'up')
            ->call('moveEventLinkedTask', $a->id, 'up'); // first item: no-op

        $this->assertSame([$a->id, $c->id, $b->id], array_column($component->get('eventLinkedTasks'), 'id'));

        $component->call('moveEventLinkedTask', $a->id, 'down');
        $this->assertSame([$c->id, $a->id, $b->id], array_column($component->get('eventLinkedTasks'), 'id'));
    }

    public function test_the_event_form_hints_at_the_categorys_own_link(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Wettkampfvorbereitung']);
        $category = EventCategory::factory()->for($user)->pomodoro()->create([
            'task_source' => 'project', 'linked_project_id' => $project->id,
        ]);

        $component = Livewire::actingAs($user)->test(Schedule::class)
            ->set('eventKind', 'category')
            ->set('eventCategoryId', $category->id);

        $this->assertSame('Wettkampfvorbereitung', $component->instance()->eventCategoryLinkLabel);
    }

    public function test_no_hint_without_a_link_or_for_a_termin_or_a_non_pomodoro_category(): void
    {
        $user = User::factory()->create();
        $plain = EventCategory::factory()->for($user)->pomodoro()->create();
        $inert = EventCategory::factory()->for($user)->create(['task_source' => 'text', 'linked_text' => 'Zimmer aufräumen']);

        $component = Livewire::actingAs($user)->test(Schedule::class);
        $this->assertNull($component->instance()->eventCategoryLinkLabel);

        $component->set('eventKind', 'category')->set('eventCategoryId', $plain->id);
        $this->assertNull($component->instance()->eventCategoryLinkLabel);

        $component->set('eventCategoryId', $inert->id);
        $this->assertNull($component->instance()->eventCategoryLinkLabel);
    }
}
