<?php

namespace Tests\Feature\Commands;

use App\Models\EventCategory;
use App\Models\ScheduleEvent;
use App\Models\User;
use App\Services\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdvancePomodoroPhasesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_it_advances_an_elapsed_autostart_work_session_and_notifies(): void
    {
        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once();
        });

        $user = User::factory()->create([
            'pomodoro_work' => 25, 'pomodoro_short_break' => 5, 'pomodoro_long_break' => 15, 'pomodoro_long_every' => 4,
            'pomodoro_autostart' => true,
            'notify_pomo_start' => true,
            'notify_break_start' => true,
        ]);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        Carbon::setTestNow('2026-06-26 14:00:00');
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['pomodoro_phase' => 'work', 'pomodoro_cycle' => 1, 'pomodoro_started_at' => '2026-06-26 14:00:00']);

        Carbon::setTestNow('2026-06-26 14:25:00');

        $this->artisan('app:advance-pomodoro-phases')->assertSuccessful();

        $event->refresh();
        $this->assertSame('short_break', $event->pomodoro_phase);
        $this->assertSame(1, $event->pomodoro_cycle);
        $this->assertNotNull($event->pomodoro_started_at);
        $this->assertTrue($event->pomodoro_started_at->equalTo(Carbon::parse('2026-06-26 14:25:00')));
    }

    public function test_it_freezes_an_elapsed_session_when_autostart_is_disabled_and_never_notifies(): void
    {
        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldNotReceive('notify');
        });

        $user = User::factory()->create([
            'pomodoro_work' => 25, 'pomodoro_short_break' => 5, 'pomodoro_long_break' => 15, 'pomodoro_long_every' => 4,
            'pomodoro_autostart' => false,
            'notify_pomo_start' => true,
            'notify_break_start' => true,
        ]);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        Carbon::setTestNow('2026-06-26 14:00:00');
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['pomodoro_phase' => 'work', 'pomodoro_cycle' => 1, 'pomodoro_started_at' => '2026-06-26 14:00:00']);

        Carbon::setTestNow('2026-06-26 14:25:00');

        $this->artisan('app:advance-pomodoro-phases')->assertSuccessful();

        $event->refresh();
        $this->assertSame('work', $event->pomodoro_phase); // stays the just-finished phase
        $this->assertSame(1, $event->pomodoro_cycle);
        $this->assertNull($event->pomodoro_started_at); // frozen
    }

    public function test_it_leaves_a_session_that_has_not_yet_elapsed_untouched(): void
    {
        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldNotReceive('notify');
        });

        $user = User::factory()->create([
            'pomodoro_work' => 25, 'pomodoro_autostart' => true,
        ]);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        Carbon::setTestNow('2026-06-26 14:00:00');
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->create(['pomodoro_phase' => 'work', 'pomodoro_cycle' => 1, 'pomodoro_started_at' => '2026-06-26 14:00:00']);

        Carbon::setTestNow('2026-06-26 14:10:00'); // only 10' elapsed of a 25' work session

        $this->artisan('app:advance-pomodoro-phases')->assertSuccessful();

        $event->refresh();
        $this->assertSame('work', $event->pomodoro_phase);
        $this->assertSame(1, $event->pomodoro_cycle);
        $this->assertNotNull($event->pomodoro_started_at);
        $this->assertTrue($event->pomodoro_started_at->equalTo(Carbon::parse('2026-06-26 14:00:00')));
    }

    public function test_a_never_started_event_is_left_completely_untouched(): void
    {
        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldNotReceive('notify');
        });

        $user = User::factory()->create(['pomodoro_autostart' => true]);
        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')->create([
            'pomodoro_phase' => null, 'pomodoro_cycle' => 1, 'pomodoro_started_at' => null,
        ]);

        $this->artisan('app:advance-pomodoro-phases')->assertSuccessful();

        $event->refresh();
        $this->assertNull($event->pomodoro_phase);
        $this->assertNull($event->pomodoro_started_at);
        $this->assertSame(1, $event->pomodoro_cycle);
    }

    public function test_it_advances_elapsed_sessions_for_two_different_users_in_a_single_run(): void
    {
        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->twice();
        });

        $userA = User::factory()->create([
            'pomodoro_work' => 25, 'pomodoro_short_break' => 5, 'pomodoro_long_break' => 15, 'pomodoro_long_every' => 4,
            'pomodoro_autostart' => true, 'notify_pomo_start' => true, 'notify_break_start' => true,
        ]);
        $userB = User::factory()->create([
            'pomodoro_work' => 25, 'pomodoro_short_break' => 5, 'pomodoro_long_break' => 15, 'pomodoro_long_every' => 4,
            'pomodoro_autostart' => true, 'notify_pomo_start' => true, 'notify_break_start' => true,
        ]);
        $categoryA = EventCategory::factory()->for($userA)->create(['pomodoro_enabled' => true]);
        $categoryB = EventCategory::factory()->for($userB)->create(['pomodoro_enabled' => true]);

        Carbon::setTestNow('2026-06-26 14:00:00');
        $eventA = ScheduleEvent::factory()->for($userA)->for($categoryA, 'category')
            ->create(['pomodoro_phase' => 'work', 'pomodoro_cycle' => 1, 'pomodoro_started_at' => '2026-06-26 14:00:00']);
        $eventB = ScheduleEvent::factory()->for($userB)->for($categoryB, 'category')
            ->create(['pomodoro_phase' => 'work', 'pomodoro_cycle' => 1, 'pomodoro_started_at' => '2026-06-26 14:00:00']);

        Carbon::setTestNow('2026-06-26 14:25:00');

        $this->artisan('app:advance-pomodoro-phases')->assertSuccessful();

        $eventA->refresh();
        $eventB->refresh();
        $this->assertSame('short_break', $eventA->pomodoro_phase);
        $this->assertNotNull($eventA->pomodoro_started_at);
        $this->assertSame('short_break', $eventB->pomodoro_phase);
        $this->assertNotNull($eventB->pomodoro_started_at);
    }
}
