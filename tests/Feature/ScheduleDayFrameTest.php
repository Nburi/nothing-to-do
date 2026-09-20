<?php

namespace Tests\Feature;

use App\Livewire\Schedule;
use App\Livewire\Settings;
use App\Livewire\WeekPlan;
use App\Models\ScheduleDayBound;
use App\Models\ScheduleEvent;
use App\Models\User;
use App\Services\DayWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tagesrahmen: the three tiers (default / weekday / single date) and what they
 * do to the grids that read them.
 */
class ScheduleDayFrameTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $this->actingAs($user);

        return $user;
    }

    // ── Settings: the default ────────────────────────────────────────

    public function test_the_settings_card_saves_the_default_frame(): void
    {
        $user = $this->actingUser();

        Livewire::test(Settings::class)
            ->set('dayStartTime', '07:30')
            ->set('dayEndTime', '22:00')
            ->call('saveDayFrame')
            ->assertHasNoErrors();

        $this->assertSame('07:30', $user->fresh()->day_start_time);
        $this->assertSame('22:00', $user->fresh()->day_end_time);
    }

    public function test_the_settings_card_refuses_a_day_shorter_than_two_hours(): void
    {
        $user = $this->actingUser();

        Livewire::test(Settings::class)
            ->set('dayStartTime', '10:00')
            ->set('dayEndTime', '11:00')
            ->call('saveDayFrame')
            ->assertHasErrors('dayEndTime');

        // Rejected outright rather than silently widened — the two selects are
        // the one place the user states this, so changing it quietly would lie.
        $this->assertSame('06:00', $user->fresh()->day_start_time);
    }

    public function test_the_settings_card_seeds_from_the_stored_default(): void
    {
        $this->actingUser(['day_start_time' => '05:30', 'day_end_time' => '21:30']);

        Livewire::test(Settings::class)
            ->assertSet('dayStartTime', '05:30')
            ->assertSet('dayEndTime', '21:30');
    }

    // ── Zeitplan: one concrete date ──────────────────────────────────

    public function test_the_zeitplan_writes_a_single_day_override(): void
    {
        $user = $this->actingUser();
        $date = $user->localToday()->toDateString();

        Livewire::test(Schedule::class)
            ->call('openDateBounds', $date)
            ->assertSet('boundsScope', 'date')
            ->assertSet('boundsStart', '06:00')
            ->set('boundsStart', '09:00')
            ->set('boundsEnd', '21:00')
            ->call('saveDayBounds')
            ->assertHasNoErrors()
            ->assertSet('boundsScope', null);

        $bound = ScheduleDayBound::forUser($user)->forDate($date)->sole();
        $this->assertSame('09:00', $bound->start_time);
        $this->assertSame('21:00', $bound->end_time);
    }

    public function test_resetting_a_single_day_drops_its_row_and_falls_back_to_the_weekday(): void
    {
        $user = $this->actingUser(['weekday_day_bounds' => ['1' => ['start' => '07:00', 'end' => '22:00']]]);
        // A Monday, so the weekday override below it is the one it falls back to.
        $date = '2026-09-21';
        DayWindow::setDate($user, $date, '10:00', '20:00');

        Livewire::test(Schedule::class)
            ->call('openDateBounds', $date)
            ->assertSet('boundsHasOverride', true)
            ->call('resetDayBounds');

        $this->assertSame(0, ScheduleDayBound::forUser($user)->count());
        $this->assertSame('weekday', DayWindow::settingForDate($user->fresh(), $date)['source']);
    }

    public function test_a_stale_or_malformed_date_never_opens_the_sheet(): void
    {
        $this->actingUser();

        Livewire::test(Schedule::class)
            ->call('openDateBounds', 'not-a-date')
            ->assertSet('boundsScope', null);
    }

    public function test_the_zeitplan_renders_a_frame_chip_per_day(): void
    {
        $this->actingUser();

        $this->get('/app/schedule')
            ->assertOk()
            ->assertSee('openDateBounds', false)
            ->assertSee('06:00–23:00', false);
    }

    // ── Wochenplan: one weekday ──────────────────────────────────────

    public function test_the_wochenplan_writes_a_weekday_override(): void
    {
        $user = $this->actingUser();

        Livewire::test(WeekPlan::class)
            ->call('openWeekdayBounds', 6)
            ->assertSet('boundsScope', 'weekday')
            ->set('boundsStart', '09:00')
            ->set('boundsEnd', '23:30')
            ->call('saveDayBounds')
            ->assertHasNoErrors();

        $this->assertSame(
            ['start' => 9 * 60, 'end' => 23 * 60 + 30, 'source' => 'weekday'],
            DayWindow::settingForWeekday($user->fresh(), 6),
        );
    }

    public function test_a_weekday_override_shows_up_on_that_weekdays_dates_in_the_zeitplan(): void
    {
        $user = $this->actingUser();
        DayWindow::setWeekday($user, 6, '09:00', '23:30');

        // A week containing Saturday 2026-09-26.
        $settings = Livewire::test(Schedule::class)
            ->set('weekStart', '2026-09-21')
            ->instance()
            ->daySettings;

        $this->assertSame('weekday', $settings['2026-09-26']['source']);
        $this->assertSame(9 * 60, $settings['2026-09-26']['start']);
        $this->assertSame('default', $settings['2026-09-22']['source']);
    }

    public function test_an_out_of_range_weekday_never_opens_the_sheet(): void
    {
        $this->actingUser();

        Livewire::test(WeekPlan::class)
            ->call('openWeekdayBounds', 9)
            ->assertSet('boundsScope', null);
    }

    // ── What the frame does to the grid ──────────────────────────────

    public function test_a_block_outside_the_frame_still_renders_and_widens_the_grid(): void
    {
        $user = $this->actingUser(['day_start_time' => '09:00', 'day_end_time' => '18:00']);
        ScheduleEvent::factory()->for($user)->on($user->localToday()->toDateString())
            ->at('06:30', '07:15')->create(['title' => 'Frühtraining']);

        $response = $this->get('/app/schedule');

        $response->assertOk();
        $response->assertSee('Frühtraining', false);
        // 06:00 is outside the 09:00–18:00 setting, so the grid must have widened
        // down to it rather than hiding the block.
        $response->assertSee('data-day-start="360"', false);
    }

    public function test_a_narrower_frame_leaves_the_grid_the_same_height_and_raises_the_scale(): void
    {
        $user = $this->actingUser(['day_start_time' => '08:00', 'day_end_time' => '18:00']);

        // 08:00-18:00 plus the two night margins is 11h; the desktop grid aims for
        // a constant height, so the scale rises instead of the page shrinking.
        $span = (18 * 60 + DayWindow::NIGHT_MARGIN) - (8 * 60 - DayWindow::NIGHT_MARGIN);
        $expectedHeight = (int) round($span * DayWindow::ppm($span));

        $this->get('/app/schedule')
            ->assertOk()
            ->assertSee('height: '.$expectedHeight.'px', false);
    }

    public function test_the_night_margin_renders_as_a_dimmed_band(): void
    {
        $this->actingUser(['day_start_time' => '08:00', 'day_end_time' => '18:00']);

        $this->get('/app/schedule')
            ->assertOk()
            ->assertSee('tl-night tl-night-top', false)
            ->assertSee('tl-night tl-night-bottom', false);
    }

    public function test_the_prepare_ritual_uses_the_same_frame_as_the_zeitplan(): void
    {
        $user = $this->actingUser(['day_start_time' => '08:00', 'day_end_time' => '18:00']);
        $target = $user->prepareTargetDate();
        DayWindow::setDate($user, $target, '10:00', '20:00');

        $this->get('/app/prepare')
            ->assertOk()
            // 10:00 minus the night margin.
            ->assertSee('data-day-start="'.(10 * 60 - DayWindow::NIGHT_MARGIN).'"', false);
    }

    public function test_another_users_day_bound_is_invisible(): void
    {
        $stranger = User::factory()->create();
        $stranger->scheduleDayBounds()->create(['date' => '2026-09-21', 'start_time' => '03:00', 'end_time' => '23:30']);

        $user = $this->actingUser();

        $this->assertSame('default', DayWindow::settingForDate($user, '2026-09-21')['source']);
    }

    public function test_the_frame_survives_a_timezone_offset_without_shifting(): void
    {
        // The frame is wall-clock, not an instant: a user in UTC+2 still gets
        // exactly the window they picked, not one shifted by two hours.
        $user = $this->actingUser(['day_start_time' => '07:00', 'day_end_time' => '22:00', 'timezone_offset' => 2]);

        $setting = DayWindow::settingForDate($user, Carbon::parse('2026-09-21'));

        $this->assertSame(7 * 60, $setting['start']);
        $this->assertSame(22 * 60, $setting['end']);
    }
}
