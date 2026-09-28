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

    /**
     * Review round 1: the hour loops floored the frame's start to a whole hour.
     * The frame now routinely starts on a half hour (the night margin), so the
     * first label and grid line were placed at a negative offset — drawn over
     * the day-header row above the grid.
     */
    public function test_no_hour_label_is_placed_above_the_grid(): void
    {
        $this->actingUser(['day_start_time' => '06:00', 'day_end_time' => '23:00']);

        foreach (['/app/schedule', '/app/weekplan', '/app/prepare'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertDoesNotMatchRegularExpression(
                '/style="top: -\d/',
                $html,
                $url.' places something above the top of its own grid',
            );
        }
    }

    /**
     * Review round 3: the chip names the setting, the axis can be wider than
     * it. Without a line saying so the chip reads as broken.
     */
    public function test_the_grid_says_when_it_widened_past_the_setting(): void
    {
        $user = $this->actingUser(['day_start_time' => '09:00', 'day_end_time' => '18:00']);
        ScheduleEvent::factory()->for($user)->on($user->localToday()->toDateString())
            ->at('06:30', '07:15')->create(['title' => 'Frühtraining']);

        $this->get('/app/schedule')
            ->assertOk()
            ->assertSee('liegt ausserhalb deines Tagesrahmens', false);
    }

    public function test_a_grid_that_fits_says_nothing_about_widening(): void
    {
        $user = $this->actingUser(['day_start_time' => '06:00', 'day_end_time' => '23:00']);
        ScheduleEvent::factory()->for($user)->on($user->localToday()->toDateString())
            ->at('09:00', '10:00')->create(['title' => 'Schule']);

        $this->get('/app/schedule')
            ->assertOk()
            ->assertDontSee('liegt ausserhalb deines Tagesrahmens', false);
    }

    /**
     * Review round 3: "Wochentag verwenden" is only true when that weekday has
     * an override of its own. The link names the frame it actually returns to.
     */
    public function test_the_reset_link_names_the_frame_it_returns_to(): void
    {
        $user = $this->actingUser(['weekday_day_bounds' => ['1' => ['start' => '07:00', 'end' => '22:00']]]);
        DayWindow::setDate($user, '2026-09-21', '10:00', '20:00');

        Livewire::test(Schedule::class)
            ->call('openDateBounds', '2026-09-21')
            ->assertSee('Zurücksetzen auf 07:00–22:00');
    }

    public function test_a_weekday_reset_link_names_the_default(): void
    {
        $this->actingUser(['day_start_time' => '06:30', 'day_end_time' => '22:30']);

        Livewire::test(WeekPlan::class)
            ->call('openWeekdayBounds', 6)
            ->assertDontSee('Zurücksetzen auf');

        Livewire::test(WeekPlan::class)
            ->call('openWeekdayBounds', 6)
            ->set('boundsStart', '09:00')
            ->set('boundsEnd', '23:30')
            ->call('saveDayBounds')
            ->call('openWeekdayBounds', 6)
            ->assertSee('Zurücksetzen auf 06:30–22:30');
    }

    /**
     * Review round 0 (HALB): the mobile Wochenplan shows one weekday at a time
     * but rendered every one of them with the shared Mon–Sun frame — so
     * shortening Saturday on that very page changed nothing about the size of
     * Saturday's blocks. The shared frame is only forced where seven columns
     * stand next to one hour gutter, which is the desktop grid, not this one.
     */
    public function test_the_mobile_weekplan_scales_each_weekday_to_its_own_frame(): void
    {
        $user = $this->actingUser();
        DayWindow::setWeekday($user, 6, '09:00', '14:00');

        $html = $this->get('/app/weekplan')->assertOk()->getContent();

        // Saturday: 09:00–14:00 plus the two night margins.
        $this->assertStringContainsString('data-weekday="6"', $html);
        $this->assertMatchesRegularExpression(
            '/data-weekday="6"[^>]*data-span="360"[^>]*data-day-start="510"/s',
            preg_replace('/\s+/', ' ', $html),
            'Saturday does not carry its own span on the mobile Wochenplan',
        );
        // Monday still follows the default.
        $this->assertMatchesRegularExpression(
            '/data-weekday="1"[^>]*data-span="1080"[^>]*data-day-start="330"/s',
            preg_replace('/\s+/', ' ', $html),
            'Monday should be unaffected by a Saturday override',
        );
    }

    /**
     * The week grid's own frame is the union of its seven days' settings — one
     * hour gutter cannot serve seven scales. Asserted on the rendered grid
     * rather than on a helper, because the components assemble the union
     * themselves from the settings they already compute for the chips.
     */
    public function test_the_week_grid_takes_the_widest_setting_of_its_seven_days(): void
    {
        $user = $this->actingUser(['day_start_time' => '08:00', 'day_end_time' => '18:00']);
        // Saturday alone runs longer at both ends.
        DayWindow::setWeekday($user, 6, '06:30', '22:00');

        $span = (22 * 60 + DayWindow::NIGHT_MARGIN) - (6 * 60 + 30 - DayWindow::NIGHT_MARGIN);

        Livewire::test(Schedule::class)
            ->set('weekStart', '2026-09-21')
            ->assertSee('data-span="'.$span.'"', false)
            ->assertSee('data-day-start="'.(6 * 60).'"', false);
    }

    public function test_the_desktop_weekplan_still_shares_one_scale(): void
    {
        $user = $this->actingUser();
        DayWindow::setWeekday($user, 6, '09:00', '14:00');

        // Seven columns next to a single hour gutter cannot have seven scales;
        // the widest setting in the week wins, and each column's own frame is
        // shown by how far its dimmed night band reaches instead.
        $frame = Livewire::test(WeekPlan::class)->instance();

        $this->assertSame(9 * 60, $frame->weekdayFrames[6]['settingStart']);
        $this->assertSame(6 * 60, $frame->weekdaySettings[1]['start']);
    }

    /**
     * Review round 0 (HALB): step 3 embeds the Zeitplan's timeline verbatim, so
     * it has the lift — and was the only grid that never said so. On touch that
     * is the difference between finding it and not: the tap it needs was a
     * gesture that did nothing at all before this feature.
     */
    public function test_the_prepare_ritual_says_how_to_reach_a_short_blocks_editor(): void
    {
        $this->actingUser();

        $this->get('/app/prepare')
            ->assertOk()
            ->assertSee('Kurze Blöcke antippen, um sie aufzurichten', false);
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
