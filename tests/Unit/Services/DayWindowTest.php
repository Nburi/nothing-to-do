<?php

namespace Tests\Unit\Services;

use App\Models\ScheduleDayBound;
use App\Models\ScheduleEvent;
use App\Models\User;
use App\Services\DayWindow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DayWindowTest extends TestCase
{
    use RefreshDatabase;

    // ── Tier resolution ──────────────────────────────────────────────

    public function test_an_untouched_account_gets_the_old_hardcoded_window(): void
    {
        $user = User::factory()->create();

        $this->assertSame(['start' => 6 * 60, 'end' => 23 * 60], DayWindow::defaultSetting($user));
    }

    public function test_a_weekday_override_beats_the_default(): void
    {
        $user = User::factory()->create(['weekday_day_bounds' => ['6' => ['start' => '09:00', 'end' => '23:30']]]);

        $saturday = DayWindow::settingForWeekday($user, 6);
        $monday = DayWindow::settingForWeekday($user, 1);

        $this->assertSame(['start' => 9 * 60, 'end' => 23 * 60 + 30, 'source' => 'weekday'], $saturday);
        $this->assertSame('default', $monday['source']);
    }

    public function test_a_date_override_beats_the_weekday_override(): void
    {
        $user = User::factory()->create(['weekday_day_bounds' => ['1' => ['start' => '07:00', 'end' => '22:00']]]);
        // 2026-09-21 is a Monday.
        $user->scheduleDayBounds()->create(['date' => '2026-09-21', 'start_time' => '10:00', 'end_time' => '20:00']);

        $setting = DayWindow::settingForDate($user, '2026-09-21');

        $this->assertSame(['start' => 10 * 60, 'end' => 20 * 60, 'source' => 'date'], $setting);
    }

    public function test_a_date_with_no_row_of_its_own_falls_through_to_its_weekday(): void
    {
        $user = User::factory()->create(['weekday_day_bounds' => ['1' => ['start' => '07:00', 'end' => '22:00']]]);

        $setting = DayWindow::settingForDate($user, '2026-09-21');

        $this->assertSame(['start' => 7 * 60, 'end' => 22 * 60, 'source' => 'weekday'], $setting);
    }

    public function test_a_garbage_override_is_ignored_rather_than_breaking_the_page(): void
    {
        $user = User::factory()->create(['weekday_day_bounds' => ['3' => ['start' => 'nonsense', 'end' => '??']]]);

        $this->assertSame('default', DayWindow::settingForWeekday($user, 3)['source']);
    }

    // ── Writing ──────────────────────────────────────────────────────

    public function test_clearing_the_last_weekday_override_stores_null_again(): void
    {
        $user = User::factory()->create();

        DayWindow::setWeekday($user, 6, '09:00', '23:00');
        $this->assertNotNull($user->fresh()->weekday_day_bounds);

        DayWindow::clearWeekday($user->fresh(), 6);
        $this->assertNull($user->fresh()->weekday_day_bounds);
    }

    public function test_setting_a_date_twice_updates_the_same_row(): void
    {
        $user = User::factory()->create();

        DayWindow::setDate($user, '2026-09-21', '08:00', '20:00');
        DayWindow::setDate($user, '2026-09-21', '09:00', '21:00');

        $this->assertSame(1, ScheduleDayBound::forUser($user)->count());
        $this->assertSame('09:00', ScheduleDayBound::forUser($user)->sole()->start_time);
    }

    public function test_a_day_shorter_than_the_minimum_is_widened_on_write(): void
    {
        $user = User::factory()->create();

        DayWindow::setDefault($user, '10:00', '10:30');

        $this->assertSame(['start' => 10 * 60, 'end' => 10 * 60 + DayWindow::MIN_SPAN], DayWindow::defaultSetting($user->fresh()));
    }

    // ── Frames ───────────────────────────────────────────────────────

    public function test_a_frame_keeps_a_night_margin_on_each_side(): void
    {
        $frame = DayWindow::frame(8 * 60, 21 * 60);

        $this->assertSame(8 * 60 - DayWindow::NIGHT_MARGIN, $frame['start']);
        $this->assertSame(21 * 60 + DayWindow::NIGHT_MARGIN, $frame['end']);
        $this->assertFalse($frame['expanded']);
    }

    public function test_a_frame_expands_to_whole_hours_around_a_block_outside_it(): void
    {
        $frame = DayWindow::frame(8 * 60, 21 * 60, [[6 * 60 + 15, 7 * 60], [22 * 60, 23 * 60 + 20]]);

        $this->assertSame(6 * 60, $frame['start']);
        $this->assertSame(24 * 60, $frame['end']);
        $this->assertTrue($frame['expanded']);
        // The stored setting is never rewritten by the expansion.
        $this->assertSame(8 * 60, $frame['settingStart']);
        $this->assertSame(21 * 60, $frame['settingEnd']);
    }

    public function test_the_footprint_used_for_expansion_includes_the_travel_time(): void
    {
        $user = User::factory()->create();
        $event = ScheduleEvent::factory()->for($user)->at('08:00', '09:00')->create([
            'buffer_before' => 45,
            'buffer_after' => 30,
        ]);

        $this->assertSame([[7 * 60 + 15, 9 * 60 + 30]], DayWindow::rangesFrom([$event]));

        // A travel time reaching before the frame starts has to widen it too, or
        // half of it would be cut off with nothing saying so.
        $frame = DayWindow::frame(8 * 60, 21 * 60, DayWindow::rangesFrom([$event]));
        $this->assertSame(7 * 60, $frame['start']);
    }

    // ── Scale ────────────────────────────────────────────────────────

    public function test_a_shorter_day_makes_the_blocks_taller_rather_than_the_page_shorter(): void
    {
        $long = DayWindow::ppm(18 * 60);   // the default 06:00-23:00 plus its night margins
        $short = DayWindow::ppm(12 * 60);

        $this->assertGreaterThan($long, $short);
        // The default frame stays within a hair of the old fixed 0.6 px/min, so
        // nothing about an untouched account's grid visibly changes.
        $this->assertEqualsWithDelta(0.6, $long, 0.05);
    }

    public function test_the_scale_is_bounded_at_both_ends(): void
    {
        $this->assertSame(DayWindow::MAX_PPM, DayWindow::ppm(60));
        $this->assertSame(DayWindow::MIN_PPM, DayWindow::ppm(24 * 60));
    }
}
