<?php

namespace Tests\Feature;

use App\Models\EventCategory;
use App\Models\Reminder;
use App\Models\ScheduleEvent;
use App\Models\User;
use App\Services\PushNotifier;
use App\Services\Reminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** The reminder rules: writes, the badge window and exactly when pushes are due. */
class RemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Tuesday 2026-10-27, noon UTC; the user is on UTC (offset 0) so local = server time. */
    private function user(array $attrs = []): User
    {
        Carbon::setTestNow('2026-10-27 12:00:00');

        return User::factory()->create($attrs + ['timezone_offset' => 0, 'timezone_auto_dst' => false]);
    }

    // ── Writes ────────────────────────────────────────────────────────

    public function test_create_stores_a_trimmed_title_and_defaults_both_pushes_on(): void
    {
        $user = $this->user();

        $r = Reminders::create($user, ['title' => '  Turnschuhe mitnehmen ', 'remind_on' => '2026-10-28']);

        $this->assertSame('Turnschuhe mitnehmen', $r->title);
        $this->assertTrue($r->notify_evening_before);
        $this->assertTrue($r->notify_on_day);
        $this->assertSame('2026-10-28', $r->remind_on->toDateString());
        $this->assertNull($r->acknowledged_at);
    }

    public function test_create_rejects_an_empty_title_a_bad_date_the_past_and_a_far_future(): void
    {
        $user = $this->user();

        foreach ([
            ['title' => '   ', 'remind_on' => '2026-10-28'],
            ['title' => str_repeat('x', Reminders::TITLE_MAX + 1), 'remind_on' => '2026-10-28'],
            ['title' => 'A', 'remind_on' => 'morgen'],
            ['title' => 'A', 'remind_on' => '2026-10-26'],
            ['title' => 'A', 'remind_on' => '2028-01-01'],
            ['remind_on' => '2026-10-28'],
        ] as $data) {
            try {
                Reminders::create($user, $data);
                $this->fail('expected a validation error for '.json_encode($data));
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, Reminder::count());
    }

    public function test_today_is_a_valid_day(): void
    {
        $user = $this->user();

        $this->assertSame('2026-10-27', Reminders::create($user, ['title' => 'Heute', 'remind_on' => '2026-10-27'])->remind_on->toDateString());
    }

    public function test_create_is_capped(): void
    {
        $user = $this->user();
        Reminder::factory()->count(Reminders::MAX_UPCOMING)->for($user)->create();

        $this->expectException(ValidationException::class);
        Reminders::create($user, ['title' => 'Eins zu viel', 'remind_on' => '2026-10-29']);
    }

    public function test_moving_a_reminder_clears_its_acknowledgement_and_push_stamps_but_a_rename_does_not(): void
    {
        $user = $this->user();
        $r = Reminder::factory()->for($user)->create(['remind_on' => '2026-10-28', 'acknowledged_at' => now(), 'notified_before_at' => now(), 'notified_on_at' => now()]);

        Reminders::update($user, $r, ['title' => 'Anders']);
        $r->refresh();
        $this->assertNotNull($r->acknowledged_at);
        $this->assertNotNull($r->notified_before_at);

        Reminders::update($user, $r, ['remind_on' => '2026-10-30']);
        $r->refresh();
        $this->assertNull($r->acknowledged_at);
        $this->assertNull($r->notified_before_at);
        $this->assertNull($r->notified_on_at);
    }

    public function test_a_title_edit_does_not_fail_on_a_reminder_whose_day_has_passed(): void
    {
        $user = $this->user();
        $r = Reminder::factory()->for($user)->create(['remind_on' => '2026-10-20']);

        Reminders::update($user, $r, ['title' => 'Umbenannt', 'remind_on' => '2026-10-20']);

        $this->assertSame('Umbenannt', $r->fresh()->title);
    }

    public function test_acknowledge_toggles(): void
    {
        $user = $this->user();
        $r = Reminder::factory()->for($user)->create();

        $this->assertNotNull(Reminders::acknowledge($r)->acknowledged_at);
        $this->assertNull(Reminders::acknowledge($r, false)->acknowledged_at);
    }

    // ── Badge window ──────────────────────────────────────────────────

    public function test_badges_cover_today_through_two_days_ahead_only(): void
    {
        $user = $this->user();
        foreach (['2026-10-26' => 'gestern', '2026-10-27' => 'heute', '2026-10-28' => 'morgen', '2026-10-29' => 'übermorgen', '2026-10-30' => 'zu früh'] as $day => $title) {
            Reminder::factory()->for($user)->create(['remind_on' => $day, 'title' => $title]);
        }

        $this->assertSame(['heute', 'morgen', 'übermorgen'], Reminders::badges($user)->pluck('title')->all());
    }

    public function test_an_acknowledged_reminder_lingers_in_the_badges_for_a_few_seconds_then_leaves(): void
    {
        $user = $this->user();
        $r = Reminder::factory()->for($user)->create(['remind_on' => '2026-10-28']);

        Reminders::acknowledge($r);
        $this->assertCount(1, Reminders::badges($user));

        Carbon::setTestNow(now()->addSeconds(Reminders::ACK_LINGER_SECONDS + 1));
        $this->assertCount(0, Reminders::badges($user));
    }

    public function test_badges_and_upcoming_are_per_user(): void
    {
        $user = $this->user();
        $other = User::factory()->create();
        Reminder::factory()->for($other)->create(['remind_on' => '2026-10-28']);

        $this->assertCount(0, Reminders::badges($user));
        $this->assertCount(0, Reminders::upcoming($user));
    }

    public function test_badges_follow_the_users_local_day_not_the_servers(): void
    {
        // 23:30 UTC on the 27th is already the 28th in UTC+2.
        Carbon::setTestNow('2026-10-27 23:30:00');
        $user = User::factory()->create(['timezone_offset' => 2, 'timezone_auto_dst' => false]);
        Reminder::factory()->for($user)->create(['remind_on' => '2026-10-27']);

        $this->assertCount(0, Reminders::badges($user), 'the 27th is already yesterday locally');
    }

    // ── Push timing ───────────────────────────────────────────────────

    public function test_nothing_is_due_before_the_evening_time(): void
    {
        $user = $this->user();
        Reminder::factory()->for($user)->create(['remind_on' => '2026-10-28', 'created_at' => now()->subDay()]);

        Carbon::setTestNow('2026-10-27 18:59:00');
        $this->assertSame([], Reminders::dueNotifications($user));
    }

    public function test_the_evening_push_for_tomorrow_is_due_from_the_evening_time_on_and_not_silent(): void
    {
        $user = $this->user();
        $r = Reminder::factory()->for($user)->create(['remind_on' => '2026-10-28', 'created_at' => now()->subDay()]);

        Carbon::setTestNow('2026-10-27 19:00:00');
        $due = Reminders::dueNotifications($user);

        $this->assertCount(1, $due);
        $this->assertSame('before', $due[0]['kind']);
        $this->assertFalse($due[0]['silent']);
        $this->assertSame($r->id, $due[0]['reminder']->id);
    }

    public function test_a_reminder_created_after_its_push_moment_is_silent(): void
    {
        $user = $this->user();
        Carbon::setTestNow('2026-10-27 21:00:00');
        Reminder::factory()->for($user)->create(['remind_on' => '2026-10-28']);

        $due = Reminders::dueNotifications($user);

        $this->assertCount(1, $due);
        $this->assertTrue($due[0]['silent']);
    }

    public function test_the_per_reminder_switches_and_the_acknowledgement_and_the_stamps_are_respected(): void
    {
        $user = $this->user(['reminder_evening_time' => '19:00']);
        $old = now()->subDay();
        Reminder::factory()->for($user)->create(['remind_on' => '2026-10-28', 'notify_evening_before' => false, 'created_at' => $old]);
        Reminder::factory()->for($user)->create(['remind_on' => '2026-10-28', 'acknowledged_at' => now(), 'created_at' => $old]);
        Reminder::factory()->for($user)->create(['remind_on' => '2026-10-28', 'notified_before_at' => now(), 'created_at' => $old]);

        Carbon::setTestNow('2026-10-27 20:00:00');
        $this->assertSame([], Reminders::dueNotifications($user));
    }

    public function test_the_push_for_today_uses_the_morning_time_without_any_block(): void
    {
        $user = $this->user();
        Reminder::factory()->for($user)->create(['remind_on' => '2026-10-27', 'created_at' => now()->subDays(2)]);

        Carbon::setTestNow('2026-10-27 06:59:00');
        $this->assertSame([], Reminders::dueNotifications($user));

        Carbon::setTestNow('2026-10-27 07:00:00');
        $due = Reminders::dueNotifications($user);
        $this->assertCount(1, $due);
        $this->assertSame('on', $due[0]['kind']);
    }

    public function test_smart_timing_moves_todays_push_before_an_early_first_block_including_travel_time(): void
    {
        $user = $this->user();
        $category = EventCategory::factory()->for($user)->create();
        // First block 07:30 with 30 min of travel: leave at 07:00, push 30 min earlier = 06:30.
        ScheduleEvent::factory()->for($user)->for($category, 'category')->create(['date' => '2026-10-27', 'start_time' => '07:30', 'end_time' => '08:30', 'buffer_before' => 30]);
        ScheduleEvent::factory()->for($user)->for($category, 'category')->create(['date' => '2026-10-27', 'start_time' => '13:00', 'end_time' => '14:00']);

        $this->assertSame(6 * 60 + 30, Reminders::onDayDueMinute($user, Carbon::parse('2026-10-27')->startOfDay()));
    }

    public function test_smart_timing_never_goes_later_than_the_morning_time_and_never_before_five(): void
    {
        $user = $this->user();
        $category = EventCategory::factory()->for($user)->create();
        ScheduleEvent::factory()->for($user)->for($category, 'category')->create(['date' => '2026-10-27', 'start_time' => '13:00', 'end_time' => '14:00']);
        $day = Carbon::parse('2026-10-27')->startOfDay();

        $this->assertSame(7 * 60, Reminders::onDayDueMinute($user, $day), 'a late first block keeps the morning time');

        ScheduleEvent::factory()->for($user)->for($category, 'category')->create(['date' => '2026-10-27', 'start_time' => '05:15', 'end_time' => '06:00']);
        $this->assertSame(Reminders::EARLIEST_ON_DAY_MINUTE, Reminders::onDayDueMinute($user, $day));
    }

    public function test_with_smart_timing_off_the_morning_time_always_wins(): void
    {
        $user = $this->user(['reminder_smart_timing' => false, 'reminder_morning_time' => '08:15']);
        $category = EventCategory::factory()->for($user)->create();
        ScheduleEvent::factory()->for($user)->for($category, 'category')->create(['date' => '2026-10-27', 'start_time' => '07:30', 'end_time' => '08:30']);

        $this->assertSame(8 * 60 + 15, Reminders::onDayDueMinute($user, Carbon::parse('2026-10-27')->startOfDay()));
    }

    public function test_a_cancelled_first_block_is_ignored_by_smart_timing(): void
    {
        $user = $this->user();
        $category = EventCategory::factory()->for($user)->create();
        ScheduleEvent::factory()->for($user)->for($category, 'category')->create(['date' => '2026-10-27', 'start_time' => '06:00', 'end_time' => '07:00', 'is_cancelled' => true]);

        $this->assertSame(7 * 60, Reminders::onDayDueMinute($user, Carbon::parse('2026-10-27')->startOfDay()));
    }

    // ── The command ───────────────────────────────────────────────────

    public function test_the_command_pushes_once_stamps_and_does_not_repeat(): void
    {
        $user = $this->user();
        $r = Reminder::factory()->for($user)->create(['title' => 'Turnschuhe', 'remind_on' => '2026-10-28', 'created_at' => now()->subDay()]);
        Carbon::setTestNow('2026-10-27 19:05:00');

        $this->mock(PushNotifier::class, function ($mock) use ($user) {
            $mock->shouldReceive('notify')->once()->withArgs(fn ($u, $payload) => $u->is($user)
                && $payload['title'] === 'Morgen nicht vergessen' && $payload['body'] === 'Turnschuhe');
        });

        $this->artisan('app:send-reminder-notifications')->assertSuccessful();
        $this->artisan('app:send-reminder-notifications')->assertSuccessful();

        $this->assertNotNull($r->fresh()->notified_before_at);
    }

    public function test_the_command_only_stamps_a_silent_one(): void
    {
        $user = $this->user();
        Carbon::setTestNow('2026-10-27 21:00:00');
        $r = Reminder::factory()->for($user)->create(['remind_on' => '2026-10-28']);

        $this->mock(PushNotifier::class, fn ($mock) => $mock->shouldReceive('notify')->never());

        $this->artisan('app:send-reminder-notifications')->assertSuccessful();

        $this->assertNotNull($r->fresh()->notified_before_at);
    }

    public function test_the_command_sends_todays_push_and_never_for_an_acknowledged_reminder(): void
    {
        $user = $this->user();
        $open = Reminder::factory()->for($user)->create(['title' => 'Offen', 'remind_on' => '2026-10-27', 'created_at' => now()->subDays(2)]);
        Reminder::factory()->for($user)->acknowledged()->create(['title' => 'Schon dabei', 'remind_on' => '2026-10-27', 'created_at' => now()->subDays(2)]);
        Carbon::setTestNow('2026-10-27 07:30:00');

        $this->mock(PushNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->once()->withArgs(fn ($u, $payload) => $payload['title'] === 'Heute nicht vergessen' && $payload['body'] === 'Offen');
        });

        $this->artisan('app:send-reminder-notifications')->assertSuccessful();

        $this->assertNotNull($open->fresh()->notified_on_at);
    }

    public function test_the_command_prunes_reminders_older_than_a_month(): void
    {
        $user = $this->user();
        Reminder::factory()->for($user)->create(['remind_on' => '2026-09-20']);
        $keep = Reminder::factory()->for($user)->create(['remind_on' => '2026-10-20']);
        $this->mock(PushNotifier::class, fn ($mock) => $mock->shouldReceive('notify')->never());

        $this->artisan('app:send-reminder-notifications')->assertSuccessful();

        $this->assertSame([$keep->id], Reminder::pluck('id')->all());
    }

    public function test_every_users_due_reminder_is_pushed_in_one_run(): void
    {
        $a = $this->user();
        $b = User::factory()->create();
        Reminder::factory()->for($a)->create(['title' => 'A', 'remind_on' => '2026-10-27', 'created_at' => now()->subDays(2)]);
        Reminder::factory()->for($b)->create(['title' => 'B', 'remind_on' => '2026-10-27', 'created_at' => now()->subDays(2)]);
        Carbon::setTestNow('2026-10-27 08:00:00');

        $this->mock(PushNotifier::class, fn ($mock) => $mock->shouldReceive('notify')->twice());

        $this->artisan('app:send-reminder-notifications')->assertSuccessful();

        $this->assertSame(2, Reminder::whereNotNull('notified_on_at')->count());
    }
}
