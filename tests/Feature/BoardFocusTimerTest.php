<?php

namespace Tests\Feature;

use App\Livewire\TaskBoard;
use App\Models\EventCategory;
use App\Models\ScheduleEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BoardFocusTimerTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_focus_timer_sets_the_start_time_on_a_pomodoro_category_event(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')->create(['pomodoro_started_at' => null]);

        Livewire::test(TaskBoard::class)->call('startFocusTimer', $event->id);

        $this->assertNotNull($event->refresh()->pomodoro_started_at);
    }

    public function test_start_focus_timer_is_a_no_op_when_the_category_has_pomodoro_disabled(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => false]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')->create(['pomodoro_started_at' => null]);

        Livewire::test(TaskBoard::class)->call('startFocusTimer', $event->id);

        $this->assertNull($event->refresh()->pomodoro_started_at);
    }

    public function test_start_focus_timer_is_a_no_op_on_a_plain_appointment(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $event = ScheduleEvent::factory()->for($user)->create(['category_id' => null, 'pomodoro_started_at' => null]);

        Livewire::test(TaskBoard::class)->call('startFocusTimer', $event->id);

        $this->assertNull($event->refresh()->pomodoro_started_at);
    }

    public function test_stop_focus_timer_clears_the_start_time(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')->create(['pomodoro_started_at' => now()]);

        Livewire::test(TaskBoard::class)->call('stopFocusTimer', $event->id);

        $this->assertNull($event->refresh()->pomodoro_started_at);
    }

    public function test_a_user_cannot_start_another_users_timer(): void
    {
        $this->actingAs(User::factory()->create());
        $otherUser = User::factory()->create();
        $category = EventCategory::factory()->for($otherUser)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($otherUser)->for($category, 'category')->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(TaskBoard::class)->call('startFocusTimer', $event->id);
    }

    public function test_a_user_cannot_stop_another_users_timer(): void
    {
        $this->actingAs(User::factory()->create());
        $otherUser = User::factory()->create();
        $category = EventCategory::factory()->for($otherUser)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($otherUser)->for($category, 'category')->create(['pomodoro_started_at' => now()]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(TaskBoard::class)->call('stopFocusTimer', $event->id);
    }
}
