<?php

namespace Tests\Unit;

use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_user_scopes_to_the_owner(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        EventCategory::factory()->for($user)->create(['name' => 'Mine']);
        EventCategory::factory()->for($other)->create(['name' => 'Theirs']);

        $categories = EventCategory::forUser($user)->get();

        $this->assertCount(1, $categories);
        $this->assertSame('Mine', $categories->first()->name);
    }

    public function test_ordered_scope_sorts_by_sort_order_then_name(): void
    {
        $user = User::factory()->create();
        EventCategory::factory()->for($user)->create(['name' => 'Zebra', 'sort_order' => 0]);
        EventCategory::factory()->for($user)->create(['name' => 'Apple', 'sort_order' => 0]);
        EventCategory::factory()->for($user)->create(['name' => 'Middle', 'sort_order' => 1]);

        $names = EventCategory::forUser($user)->ordered()->pluck('name')->all();

        $this->assertSame(['Apple', 'Zebra', 'Middle'], $names);
    }

    public function test_deleting_a_category_nulls_it_on_its_events(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create();
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')->create();

        $category->delete();

        $this->assertNull($event->refresh()->category_id);
    }

    public function test_deleting_a_category_nulls_it_on_its_templates(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create();
        $template = EventTemplate::factory()->create(['user_id' => $user->id, 'category_id' => $category->id]);

        $category->delete();

        $this->assertNull($template->refresh()->category_id);
    }

    // ── Task link ─────────────────────────────────────────────────────

    public function test_deleting_a_category_detaches_its_pinned_tasks_without_deleting_them(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->todos()->create();
        $category = EventCategory::factory()->for($user)->create(['task_source' => 'tasks']);
        $category->pinnedTasks()->attach($task->id, ['sort_order' => 0]);

        $category->delete();

        $this->assertNotNull($task->fresh());
    }

    public function test_clear_task_link_resets_every_field_and_detaches_pinned_tasks(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->todos()->create();
        $category = EventCategory::factory()->for($user)->create(['task_source' => 'text', 'linked_text' => 'Zimmer aufräumen']);
        $category->pinnedTasks()->attach($task->id, ['sort_order' => 0]);

        $category->clearTaskLink();
        $category->refresh();

        $this->assertNull($category->task_source);
        $this->assertNull($category->linked_text);
        $this->assertCount(0, $category->pinnedTasks);
    }

    public function test_task_source_finished_message_is_null_for_a_text_link(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create(['task_source' => 'text', 'linked_text' => 'Etwas Freies']);

        $this->assertNull($category->taskSourceFinishedMessage());
    }

}
