<?php

namespace Tests\Feature\Api;

use App\Models\EventCategory;
use App\Models\ScheduleEvent;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScheduleEventTaskLinkApiTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'kind' => 'appointment',
            'title' => 'Lernen',
            'color' => 'forest',
            'date' => now()->toDateString(),
            'start_time' => '10:00',
            'end_time' => '11:00',
        ], $overrides);
    }

    public function test_an_event_can_be_created_with_bound_tasks_in_order(): void
    {
        $user = User::factory()->create();
        $a = Task::factory()->for($user)->todos()->create();
        $b = Task::factory()->for($user)->todos()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/schedule-events', $this->payload(['linked_task_ids' => [$b->id, $a->id]]))
            ->assertCreated()
            ->assertJsonPath('data.linked_task_ids', [$b->id, $a->id]);
    }

    public function test_bound_tasks_can_be_reordered_replaced_and_cleared_on_update(): void
    {
        $user = User::factory()->create();
        $a = Task::factory()->for($user)->todos()->create();
        $b = Task::factory()->for($user)->todos()->create();
        $event = ScheduleEvent::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/schedule-events/{$event->id}", ['linked_task_ids' => [$a->id, $b->id]])
            ->assertOk()->assertJsonPath('data.linked_task_ids', [$a->id, $b->id]);

        $this->patchJson("/api/schedule-events/{$event->id}", ['linked_task_ids' => [$b->id, $a->id]])
            ->assertOk()->assertJsonPath('data.linked_task_ids', [$b->id, $a->id]);

        // An update that doesn't mention the key leaves the binding alone.
        $this->patchJson("/api/schedule-events/{$event->id}", ['title' => 'Umbenannt'])
            ->assertOk()->assertJsonPath('data.linked_task_ids', [$b->id, $a->id]);

        $this->patchJson("/api/schedule-events/{$event->id}", ['linked_task_ids' => []])
            ->assertOk()->assertJsonPath('data.linked_task_ids', []);
    }

    public function test_duplicate_ids_are_rejected(): void
    {
        $user = User::factory()->create();
        $a = Task::factory()->for($user)->todos()->create();
        $event = ScheduleEvent::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/schedule-events/{$event->id}", ['linked_task_ids' => [$a->id, $a->id]])->assertUnprocessable();
    }

    public function test_show_and_index_expose_the_bound_tasks(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->todos()->create();
        $event = ScheduleEvent::factory()->for($user)->create(['date' => now()->toDateString()]);
        $event->syncLinkedTaskIds([$task->id]);
        Sanctum::actingAs($user);

        $this->getJson("/api/schedule-events/{$event->id}")->assertOk()->assertJsonPath('data.linked_task_ids', [$task->id]);
        $this->getJson('/api/schedule-events?date='.now()->toDateString())->assertOk()->assertJsonPath('data.0.linked_task_ids', [$task->id]);
    }

    public function test_someone_elses_task_cannot_be_bound(): void
    {
        $user = User::factory()->create();
        $foreign = Task::factory()->for(User::factory()->create())->todos()->create();
        $event = ScheduleEvent::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/schedule-events', $this->payload(['linked_task_ids' => [$foreign->id]]))->assertUnprocessable();
        $this->patchJson("/api/schedule-events/{$event->id}", ['linked_task_ids' => [$foreign->id]])->assertUnprocessable();
        $this->assertDatabaseCount('schedule_event_task_links', 0);
    }

    public function test_a_recurring_series_cannot_carry_bound_tasks(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->todos()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/schedule-events', $this->payload(['recurring' => true, 'days' => [1, 3], 'linked_task_ids' => [$task->id]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('linked_task_ids');
    }

    public function test_a_category_block_can_carry_bound_tasks_too(): void
    {
        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->pomodoro()->create();
        $task = Task::factory()->for($user)->todos()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/schedule-events', [
            'kind' => 'category',
            'category_id' => $category->id,
            'date' => now()->toDateString(),
            'start_time' => '14:00',
            'end_time' => '15:00',
            'linked_task_ids' => [$task->id],
        ])->assertCreated()->assertJsonPath('data.linked_task_ids', [$task->id]);
    }
}
