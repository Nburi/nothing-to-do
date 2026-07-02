<?php

namespace Tests\Feature;

use App\Livewire\TaskBoard;
use App\Models\EventCategory;
use App\Models\ScheduleEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class BoardScheduleFocusTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_an_active_pomodoro_category_event_is_the_focus_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Carbon::setTestNow('2026-06-26 14:10');

        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->on('2026-06-26')->at('14:00', '16:00')->create();

        $focus = Livewire::test(TaskBoard::class)->instance()->focusSession();

        $this->assertNotNull($focus);
        $this->assertSame($event->id, $focus->id);
    }

    public function test_a_category_event_starting_soon_is_the_focus_session_within_five_minutes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Carbon::setTestNow('2026-06-26 13:56');

        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->on('2026-06-26')->at('14:00', '16:00')->create();

        $this->assertNotNull(Livewire::test(TaskBoard::class)->instance()->focusSession());
    }

    public function test_a_category_event_starting_later_than_five_minutes_is_not_the_focus_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Carbon::setTestNow('2026-06-26 13:50');

        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->on('2026-06-26')->at('14:00', '16:00')->create();

        $this->assertNull(Livewire::test(TaskBoard::class)->instance()->focusSession());
    }

    public function test_a_category_without_pomodoro_enabled_is_never_the_focus_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Carbon::setTestNow('2026-06-26 14:10');

        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => false]);
        ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->on('2026-06-26')->at('14:00', '16:00')->create();

        $this->assertNull(Livewire::test(TaskBoard::class)->instance()->focusSession());
    }

    public function test_a_plain_appointment_is_never_the_focus_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Carbon::setTestNow('2026-06-26 14:10');

        ScheduleEvent::factory()->for($user)->on('2026-06-26')->at('14:00', '16:00')->create(['category_id' => null]);

        $this->assertNull(Livewire::test(TaskBoard::class)->instance()->focusSession());
    }

    public function test_a_running_timer_stays_the_focus_session_past_the_blocks_scheduled_end(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Carbon::setTestNow('2026-06-26 10:00');

        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        $event = ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->on('2026-06-26')->at('08:00', '09:00') // scheduled window already over
            ->create(['pomodoro_started_at' => '2026-06-26 08:55:00']);

        $focus = Livewire::test(TaskBoard::class)->instance()->focusSession();

        $this->assertNotNull($focus);
        $this->assertSame($event->id, $focus->id);
    }

    public function test_focus_phase_is_null_until_the_timer_is_started(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        Carbon::setTestNow('2026-06-26 14:10');

        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->on('2026-06-26')->at('14:00', '16:00')->create();

        $this->assertNull(Livewire::test(TaskBoard::class)->instance()->focusPhase());
    }

    public function test_focus_phase_reflects_the_running_pomodoro_cycle(): void
    {
        $user = User::factory()->create([
            'pomodoro_work' => 25, 'pomodoro_short_break' => 5, 'pomodoro_long_break' => 15, 'pomodoro_long_every' => 4,
        ]);
        $this->actingAs($user);
        Carbon::setTestNow('2026-06-26 14:10:00');

        $category = EventCategory::factory()->for($user)->create(['pomodoro_enabled' => true]);
        ScheduleEvent::factory()->for($user)->for($category, 'category')
            ->on('2026-06-26')->at('14:00', '16:00')
            ->create(['pomodoro_started_at' => '2026-06-26 14:00:00']);

        $phase = Livewire::test(TaskBoard::class)->instance()->focusPhase();

        $this->assertSame('work', $phase['phase']);
        $this->assertSame(1, $phase['cycle']);
        $this->assertSame(15 * 60, $phase['remaining_seconds']); // 25' work, 10' elapsed → 15' left
    }
}
