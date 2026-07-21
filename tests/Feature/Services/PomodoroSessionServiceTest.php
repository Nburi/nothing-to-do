<?php

namespace Tests\Feature\Services;

use App\Models\EventCategory;
use App\Models\ScheduleEvent;
use App\Models\User;
use App\Services\PomodoroSessionService;
use App\Services\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PomodoroSessionServiceTest extends TestCase
{
    use RefreshDatabase;

    private const RHYTHM = ['work' => 25, 'short_break' => 5, 'long_break' => 15, 'long_every' => 4];

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_start_sets_phase_cycle_and_start_time_and_notifies_when_enabled(): void
    {
        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $user = User::factory()->create(['notify_pomo_start' => true]);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')->create();

        Carbon::setTestNow('2026-06-26 14:00:00');

        app(PomodoroSessionService::class)->start($event, $user);

        $event->refresh();
        $this->assertSame('work', $event->pomodoro_phase);
        $this->assertSame(1, $event->pomodoro_cycle);
        $this->assertNotNull($event->pomodoro_started_at);
        $this->assertTrue($event->pomodoro_started_at->equalTo(Carbon::parse('2026-06-26 14:00:00')));
    }

    public function test_start_does_not_notify_when_notify_pomo_start_is_disabled(): void
    {
        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldNotReceive('notify');
        });

        $user = User::factory()->create(['notify_pomo_start' => false]);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')->create();

        app(PomodoroSessionService::class)->start($event, $user);

        $this->assertSame('work', $event->refresh()->pomodoro_phase);
    }

    public function test_stop_resets_phase_cycle_and_start_time_and_never_notifies(): void
    {
        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldNotReceive('notify');
        });

        $user = User::factory()->create(['notify_pomo_start' => true, 'notify_break_start' => true]);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['pomodoro_phase' => 'work', 'pomodoro_cycle' => 2, 'pomodoro_started_at' => now()]);

        app(PomodoroSessionService::class)->stop($event);

        $event->refresh();
        $this->assertNull($event->pomodoro_phase);
        $this->assertSame(1, $event->pomodoro_cycle);
        $this->assertNull($event->pomodoro_started_at);
    }

    public function test_transition_moves_from_work_cycle_one_to_short_break_and_notifies(): void
    {
        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $user = User::factory()->create(['notify_break_start' => true]);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['pomodoro_phase' => 'work', 'pomodoro_cycle' => 1, 'pomodoro_started_at' => '2026-06-26 14:00:00']);

        Carbon::setTestNow('2026-06-26 14:25:00');

        $result = app(PomodoroSessionService::class)->transition($event, $user, self::RHYTHM);

        $event->refresh();
        $this->assertSame(['phase' => 'short_break', 'cycle' => 1], $result);
        $this->assertSame('short_break', $event->pomodoro_phase);
        $this->assertSame(1, $event->pomodoro_cycle);
        $this->assertNotNull($event->pomodoro_started_at);
        $this->assertTrue($event->pomodoro_started_at->equalTo(Carbon::parse('2026-06-26 14:25:00')));
    }

    public function test_transition_from_work_cycle_four_goes_to_long_break(): void
    {
        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $user = User::factory()->create(['notify_break_start' => true]);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['pomodoro_phase' => 'work', 'pomodoro_cycle' => 4, 'pomodoro_started_at' => '2026-06-26 14:00:00']);

        $result = app(PomodoroSessionService::class)->transition($event, $user, self::RHYTHM);

        $this->assertSame(['phase' => 'long_break', 'cycle' => 4], $result);
        $this->assertSame('long_break', $event->refresh()->pomodoro_phase);
        $this->assertSame(4, $event->pomodoro_cycle);
    }

    public function test_skip_break_from_a_running_short_break_jumps_to_next_work_cycle_and_notifies(): void
    {
        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $user = User::factory()->create(['notify_pomo_start' => true]);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['pomodoro_phase' => 'short_break', 'pomodoro_cycle' => 1, 'pomodoro_started_at' => '2026-06-26 14:25:00']);

        $result = app(PomodoroSessionService::class)->skipBreak($event, $user, self::RHYTHM);

        $event->refresh();
        $this->assertTrue($result);
        $this->assertSame('work', $event->pomodoro_phase);
        $this->assertSame(2, $event->pomodoro_cycle);
        $this->assertNotNull($event->pomodoro_started_at);
    }

    public function test_skip_break_from_a_frozen_state_landing_on_a_break_also_jumps_past_it(): void
    {
        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $user = User::factory()->create(['notify_pomo_start' => true]);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        // Frozen right after work cycle 1 finished (autostart disabled) — a continue would land on short_break.
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['pomodoro_phase' => 'work', 'pomodoro_cycle' => 1, 'pomodoro_started_at' => null]);

        $result = app(PomodoroSessionService::class)->skipBreak($event, $user, self::RHYTHM);

        $event->refresh();
        $this->assertTrue($result);
        $this->assertSame('work', $event->pomodoro_phase);
        $this->assertSame(2, $event->pomodoro_cycle);
        $this->assertNotNull($event->pomodoro_started_at);
    }

    public function test_skip_break_returns_false_and_does_not_notify_when_current_phase_is_work(): void
    {
        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldNotReceive('notify');
        });

        $user = User::factory()->create(['notify_pomo_start' => true]);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['pomodoro_phase' => 'work', 'pomodoro_cycle' => 1, 'pomodoro_started_at' => '2026-06-26 14:00:00']);

        $result = app(PomodoroSessionService::class)->skipBreak($event, $user, self::RHYTHM);

        $event->refresh();
        $this->assertFalse($result);
        $this->assertSame('work', $event->pomodoro_phase);
        $this->assertSame(1, $event->pomodoro_cycle);
    }

    public function test_skip_break_returns_false_when_pomodoro_phase_is_null(): void
    {
        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldNotReceive('notify');
        });

        $user = User::factory()->create();
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['pomodoro_phase' => null, 'pomodoro_cycle' => 1, 'pomodoro_started_at' => null]);

        $result = app(PomodoroSessionService::class)->skipBreak($event, $user, self::RHYTHM);

        $this->assertFalse($result);
    }

    public function test_handle_tick_is_a_no_op_when_elapsed_time_is_less_than_the_phase_duration(): void
    {
        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldNotReceive('notify');
        });

        $user = User::factory()->create([
            'pomodoro_work' => 25, 'pomodoro_short_break' => 5, 'pomodoro_long_break' => 15, 'pomodoro_long_every' => 4,
            'pomodoro_autostart' => true,
        ]);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['pomodoro_phase' => 'work', 'pomodoro_cycle' => 1, 'pomodoro_started_at' => '2026-06-26 14:00:00']);

        Carbon::setTestNow('2026-06-26 14:10:00'); // only 10' elapsed of a 25' work session

        app(PomodoroSessionService::class)->handleTick($event, $user);

        $event->refresh();
        $this->assertSame('work', $event->pomodoro_phase);
        $this->assertSame(1, $event->pomodoro_cycle);
        $this->assertNotNull($event->pomodoro_started_at);
        $this->assertTrue($event->pomodoro_started_at->equalTo(Carbon::parse('2026-06-26 14:00:00')));
    }

    public function test_handle_tick_freezes_when_autostart_is_disabled_and_the_phase_has_elapsed(): void
    {
        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldNotReceive('notify');
        });

        $user = User::factory()->create([
            'pomodoro_work' => 25, 'pomodoro_short_break' => 5, 'pomodoro_long_break' => 15, 'pomodoro_long_every' => 4,
            'pomodoro_autostart' => false,
        ]);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['pomodoro_phase' => 'work', 'pomodoro_cycle' => 1, 'pomodoro_started_at' => '2026-06-26 14:00:00']);

        Carbon::setTestNow('2026-06-26 14:25:00');

        app(PomodoroSessionService::class)->handleTick($event, $user);

        $event->refresh();
        $this->assertSame('work', $event->pomodoro_phase); // stays the just-finished phase
        $this->assertSame(1, $event->pomodoro_cycle);
        $this->assertNull($event->pomodoro_started_at); // frozen
    }

    public function test_handle_tick_with_autostart_advances_exactly_one_phase_and_carries_elapsed_time_forward(): void
    {
        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $user = User::factory()->create([
            'pomodoro_work' => 25, 'pomodoro_short_break' => 5, 'pomodoro_long_break' => 15, 'pomodoro_long_every' => 4,
            'pomodoro_autostart' => true, 'notify_break_start' => true,
        ]);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['pomodoro_phase' => 'work', 'pomodoro_cycle' => 1, 'pomodoro_started_at' => '2026-06-26 14:00:00']);

        // 25' work duration elapsed by a few seconds.
        Carbon::setTestNow('2026-06-26 14:25:05');

        app(PomodoroSessionService::class)->handleTick($event, $user);

        $event->refresh();
        $this->assertSame('short_break', $event->pomodoro_phase);
        $this->assertSame(1, $event->pomodoro_cycle);
        $this->assertNotNull($event->pomodoro_started_at);
        // Original started_at + exactly the phase duration (25' = 1500s), NOT reset to "now".
        $this->assertTrue($event->pomodoro_started_at->equalTo(Carbon::parse('2026-06-26 14:00:00')->addSeconds(1500)));
    }

    public function test_handle_tick_with_autostart_cascades_through_multiple_fully_elapsed_phases(): void
    {
        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $user = User::factory()->create([
            'pomodoro_work' => 25, 'pomodoro_short_break' => 5, 'pomodoro_long_break' => 15, 'pomodoro_long_every' => 4,
            'pomodoro_autostart' => true, 'notify_pomo_start' => true,
        ]);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['pomodoro_phase' => 'work', 'pomodoro_cycle' => 1, 'pomodoro_started_at' => '2026-06-26 14:00:00']);

        // Both the 25' work phase AND the following 5' short break have fully elapsed (30' + a bit).
        Carbon::setTestNow('2026-06-26 14:30:10');

        app(PomodoroSessionService::class)->handleTick($event, $user);

        $event->refresh();
        $this->assertSame('work', $event->pomodoro_phase);
        $this->assertSame(2, $event->pomodoro_cycle);
        $this->assertNotNull($event->pomodoro_started_at);
        // Original started_at + sum of elapsed phase durations (1500s + 300s = 1800s), NOT reset to "now".
        $this->assertTrue($event->pomodoro_started_at->equalTo(Carbon::parse('2026-06-26 14:00:00')->addSeconds(1800)));
    }

    public function test_handle_tick_is_a_no_op_when_pomodoro_phase_is_null(): void
    {
        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldNotReceive('notify');
        });

        $user = User::factory()->create(['pomodoro_autostart' => true]);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['pomodoro_phase' => null, 'pomodoro_cycle' => 1, 'pomodoro_started_at' => null]);

        app(PomodoroSessionService::class)->handleTick($event, $user);

        $event->refresh();
        $this->assertNull($event->pomodoro_phase);
        $this->assertNull($event->pomodoro_started_at);
    }

    public function test_handle_tick_is_a_no_op_when_pomodoro_started_at_is_null(): void
    {
        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldNotReceive('notify');
        });

        $user = User::factory()->create(['pomodoro_autostart' => true]);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['pomodoro_phase' => 'work', 'pomodoro_cycle' => 1, 'pomodoro_started_at' => null]);

        app(PomodoroSessionService::class)->handleTick($event, $user);

        $event->refresh();
        $this->assertSame('work', $event->pomodoro_phase);
        $this->assertNull($event->pomodoro_started_at);
    }
}
