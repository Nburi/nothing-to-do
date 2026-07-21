<?php

namespace Tests\Feature;

use App\Livewire\TaskBoard;
use App\Models\EventCategory;
use App\Models\ScheduleEvent;
use App\Models\User;
use App\Services\PushNotifier;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class BoardFocusTimerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_start_focus_timer_sets_phase_cycle_and_start_time_on_a_pomodoro_category_event(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')->create();

        Livewire::test(TaskBoard::class)->call('startFocusTimer', $event->id);

        $event->refresh();
        $this->assertNotNull($event->pomodoro_started_at);
        $this->assertSame('work', $event->pomodoro_phase);
        $this->assertSame(1, $event->pomodoro_cycle);
    }

    public function test_start_focus_timer_is_a_no_op_when_the_category_has_pomodoro_disabled(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => false]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')->create();

        Livewire::test(TaskBoard::class)->call('startFocusTimer', $event->id);

        $this->assertNull($event->refresh()->pomodoro_phase);
    }

    public function test_start_focus_timer_is_a_no_op_on_a_plain_appointment(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $event = ScheduleEvent::factory()->for($user)->create(['category_id' => null]);

        Livewire::test(TaskBoard::class)->call('startFocusTimer', $event->id);

        $this->assertNull($event->refresh()->pomodoro_phase);
    }

    public function test_stop_focus_timer_resets_phase_cycle_and_start_time(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['pomodoro_phase' => 'work', 'pomodoro_cycle' => 2, 'pomodoro_started_at' => now()]);

        Livewire::test(TaskBoard::class)->call('stopFocusTimer', $event->id);

        $event->refresh();
        $this->assertNull($event->pomodoro_started_at);
        $this->assertNull($event->pomodoro_phase);
        $this->assertSame(1, $event->pomodoro_cycle);
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
        $event = ScheduleEvent::factory()->for($otherUser)->for($category, 'category')
            ->create(['pomodoro_phase' => 'work', 'pomodoro_started_at' => now()]);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(TaskBoard::class)->call('stopFocusTimer', $event->id);
    }

    public function test_handle_phase_complete_advances_automatically_when_autostart_is_enabled(): void
    {
        $user = User::factory()->create([
            'pomodoro_work' => 25, 'pomodoro_short_break' => 5, 'pomodoro_long_break' => 15, 'pomodoro_long_every' => 4,
            'pomodoro_autostart' => true,
        ]);
        $this->actingAs($user);
        Carbon::setTestNow('2026-06-26 14:25:00');

        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['pomodoro_phase' => 'work', 'pomodoro_cycle' => 1, 'pomodoro_started_at' => '2026-06-26 14:00:00']);

        Livewire::test(TaskBoard::class)->call('handlePhaseComplete', $event->id);

        $event->refresh();
        $this->assertSame('short_break', $event->pomodoro_phase);
        $this->assertSame(1, $event->pomodoro_cycle);
        $this->assertNotNull($event->pomodoro_started_at);
        $this->assertTrue($event->pomodoro_started_at->equalTo(Carbon::parse('2026-06-26 14:25:00')));
    }

    public function test_handle_phase_complete_freezes_awaiting_a_continue_when_autostart_is_disabled(): void
    {
        $user = User::factory()->create([
            'pomodoro_work' => 25, 'pomodoro_short_break' => 5, 'pomodoro_long_break' => 15, 'pomodoro_long_every' => 4,
            'pomodoro_autostart' => false,
        ]);
        $this->actingAs($user);
        Carbon::setTestNow('2026-06-26 14:25:00');

        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['pomodoro_phase' => 'work', 'pomodoro_cycle' => 1, 'pomodoro_started_at' => '2026-06-26 14:00:00']);

        Livewire::test(TaskBoard::class)->call('handlePhaseComplete', $event->id);

        $event->refresh();
        $this->assertSame('work', $event->pomodoro_phase); // stays the just-finished phase
        $this->assertNull($event->pomodoro_started_at); // frozen
    }

    public function test_handle_phase_complete_ignores_a_premature_call(): void
    {
        $user = User::factory()->create([
            'pomodoro_work' => 25, 'pomodoro_autostart' => true,
        ]);
        $this->actingAs($user);
        Carbon::setTestNow('2026-06-26 14:10:00'); // only 10' elapsed of a 25' work session

        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['pomodoro_phase' => 'work', 'pomodoro_cycle' => 1, 'pomodoro_started_at' => '2026-06-26 14:00:00']);

        Livewire::test(TaskBoard::class)->call('handlePhaseComplete', $event->id);

        $event->refresh();
        $this->assertSame('work', $event->pomodoro_phase);
        $this->assertNotNull($event->pomodoro_started_at); // still ticking, untouched
    }

    public function test_continue_phase_manually_starts_the_next_phase(): void
    {
        $user = User::factory()->create([
            'pomodoro_work' => 25, 'pomodoro_short_break' => 5, 'pomodoro_long_break' => 15, 'pomodoro_long_every' => 4,
        ]);
        $this->actingAs($user);
        Carbon::setTestNow('2026-06-26 14:30:00');

        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['pomodoro_phase' => 'work', 'pomodoro_cycle' => 1, 'pomodoro_started_at' => null]); // frozen, awaiting

        Livewire::test(TaskBoard::class)->call('continuePhase', $event->id);

        $event->refresh();
        $this->assertSame('short_break', $event->pomodoro_phase);
        $this->assertNotNull($event->pomodoro_started_at);
    }

    public function test_skip_break_jumps_from_a_running_break_straight_to_the_next_work_cycle(): void
    {
        $user = User::factory()->create([
            'pomodoro_work' => 25, 'pomodoro_short_break' => 5, 'pomodoro_long_break' => 15, 'pomodoro_long_every' => 4,
        ]);
        $this->actingAs($user);
        Carbon::setTestNow('2026-06-26 14:27:00');

        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['pomodoro_phase' => 'short_break', 'pomodoro_cycle' => 1, 'pomodoro_started_at' => '2026-06-26 14:25:00']);

        Livewire::test(TaskBoard::class)->call('skipBreak', $event->id);

        $event->refresh();
        $this->assertSame('work', $event->pomodoro_phase);
        $this->assertSame(2, $event->pomodoro_cycle);
        $this->assertNotNull($event->pomodoro_started_at);
    }

    public function test_skip_break_jumps_from_a_frozen_awaiting_break_straight_to_the_next_work_cycle(): void
    {
        $user = User::factory()->create([
            'pomodoro_work' => 25, 'pomodoro_short_break' => 5, 'pomodoro_long_break' => 15, 'pomodoro_long_every' => 4,
        ]);
        $this->actingAs($user);
        Carbon::setTestNow('2026-06-26 14:25:00');

        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        // Frozen right after work cycle 1 finished (autostart disabled) — the
        // break hasn't started yet, but skip should still bypass it.
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['pomodoro_phase' => 'work', 'pomodoro_cycle' => 1, 'pomodoro_started_at' => null]);

        Livewire::test(TaskBoard::class)->call('skipBreak', $event->id);

        $event->refresh();
        $this->assertSame('work', $event->pomodoro_phase);
        $this->assertSame(2, $event->pomodoro_cycle);
        $this->assertNotNull($event->pomodoro_started_at);
    }

    public function test_skip_break_is_a_no_op_when_the_current_phase_is_work(): void
    {
        $user = User::factory()->create(['pomodoro_work' => 25]);
        $this->actingAs($user);
        Carbon::setTestNow('2026-06-26 14:10:00');

        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['pomodoro_phase' => 'work', 'pomodoro_cycle' => 1, 'pomodoro_started_at' => '2026-06-26 14:00:00']);

        Livewire::test(TaskBoard::class)->call('skipBreak', $event->id);

        $event->refresh();
        $this->assertSame('work', $event->pomodoro_phase);
        $this->assertSame(1, $event->pomodoro_cycle);
    }

    public function test_starting_the_focus_timer_sends_a_push_notification_when_enabled(): void
    {
        $user = User::factory()->create(['notify_pomo_start' => true]);
        $this->actingAs($user);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')->create();

        $this->mock(PushNotifier::class, function ($mock) use ($user) {
            $mock->shouldReceive('notify')
                ->once()
                ->with($user, Mockery::on(fn ($payload) => is_array($payload) && isset($payload['title'], $payload['body'])));
        });

        Livewire::test(TaskBoard::class)->call('startFocusTimer', $event->id);

        $event->refresh();
        $this->assertSame('work', $event->pomodoro_phase);
        $this->assertSame(1, $event->pomodoro_cycle);
    }

    public function test_starting_the_focus_timer_sends_no_push_notification_when_disabled(): void
    {
        $user = User::factory()->create(['notify_pomo_start' => false]);
        $this->actingAs($user);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')->create();

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldNotReceive('notify');
        });

        Livewire::test(TaskBoard::class)->call('startFocusTimer', $event->id);

        $event->refresh();
        $this->assertNotNull($event->pomodoro_started_at);
        $this->assertSame('work', $event->pomodoro_phase);
        $this->assertSame(1, $event->pomodoro_cycle);
    }
}
