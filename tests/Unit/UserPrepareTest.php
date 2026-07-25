<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UserPrepareTest extends TestCase
{
    use RefreshDatabase;

    public function test_prepare_target_date_is_tomorrow_in_evening_mode(): void
    {
        $user = User::factory()->make(['timezone_offset' => 0, 'prepare_time_of_day' => 'evening']);

        $this->assertTrue($user->prepareTargetDate()->isSameDay(Carbon::tomorrow()));
    }

    public function test_prepare_target_date_is_today_in_morning_mode(): void
    {
        $user = User::factory()->make(['timezone_offset' => 0, 'prepare_time_of_day' => 'morning']);

        $this->assertTrue($user->prepareTargetDate()->isSameDay(Carbon::today()));
    }

    public function test_has_prepared_today_is_false_when_never_prepared(): void
    {
        $user = User::factory()->make(['timezone_offset' => 0, 'prepared_on' => null]);

        $this->assertFalse($user->hasPreparedToday());
    }

    public function test_has_prepared_today_is_true_when_prepared_on_matches_local_today(): void
    {
        $user = User::factory()->make(['timezone_offset' => 0, 'prepared_on' => Carbon::today()->toDateString()]);

        $this->assertTrue($user->hasPreparedToday());
    }

    public function test_has_prepared_today_is_false_for_a_stale_date(): void
    {
        $user = User::factory()->make(['timezone_offset' => 0, 'prepared_on' => Carbon::yesterday()->toDateString()]);

        $this->assertFalse($user->hasPreparedToday());
    }

    public function test_is_within_prepare_window_for_morning_mode_before_noon(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(8, 0));
        $user = User::factory()->make(['timezone_offset' => 0, 'prepare_time_of_day' => 'morning']);

        $this->assertTrue($user->isWithinPrepareWindow());

        Carbon::setTestNow(Carbon::today()->setTime(14, 0));
        $this->assertFalse($user->isWithinPrepareWindow());

        Carbon::setTestNow();
    }

    public function test_is_within_prepare_window_for_evening_mode_from_noon_on(): void
    {
        Carbon::setTestNow(Carbon::today()->setTime(18, 0));
        $user = User::factory()->make(['timezone_offset' => 0, 'prepare_time_of_day' => 'evening']);

        $this->assertTrue($user->isWithinPrepareWindow());

        Carbon::setTestNow(Carbon::today()->setTime(9, 0));
        $this->assertFalse($user->isWithinPrepareWindow());

        Carbon::setTestNow();
    }

    public function test_prepare_reminder_due_time_for_fixed_mode_is_the_chosen_time(): void
    {
        $user = User::factory()->make(['prepare_reminder_mode' => 'fixed', 'prepare_reminder_time' => '20:15']);

        $this->assertSame('20:15', $user->prepareReminderDueTime());
    }

    public function test_prepare_reminder_due_time_for_automatic_mode_is_the_fallback_per_time_of_day(): void
    {
        $morning = User::factory()->make(['prepare_reminder_mode' => 'automatic', 'prepare_time_of_day' => 'morning']);
        $evening = User::factory()->make(['prepare_reminder_mode' => 'automatic', 'prepare_time_of_day' => 'evening']);

        $this->assertSame('10:00', $morning->prepareReminderDueTime());
        $this->assertSame('21:00', $evening->prepareReminderDueTime());
    }

    public function test_prepare_reminder_due_time_is_null_when_off(): void
    {
        $user = User::factory()->make(['prepare_reminder_mode' => 'off']);

        $this->assertNull($user->prepareReminderDueTime());
    }
}
