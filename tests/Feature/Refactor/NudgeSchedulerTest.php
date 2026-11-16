<?php

namespace Tests\Feature\Refactor;

use App\Models\Evening;
use App\Models\PushSubscription;
use App\Models\SchedulePause;
use App\Models\Task;
use App\Models\User;
use App\Planning\Size;
use App\Services\FocusSession;
use App\Services\Planning\EveningNudge;
use App\Services\Planning\EveningReset;
use App\Services\PushNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Step 3.3 (A13, R2, risk C2): the evening nudge at a fixed time per weekday.
 * At most one per evening, none on paused days, none once tonight's reset was started or
 * closed, none without a time for the weekday; local time in Europe/Zurich, DST included.
 * Monday 9 November 2026 unless a test says otherwise.
 */
class NudgeSchedulerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /** @var list<array{user: int, payload: array}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->user = $this->userWith(['1' => '18:30', '2' => '18:30', '3' => '18:30', '4' => '18:30', '5' => '18:30', '7' => '18:00']);
        $this->fakePush();
    }

    private function userWith(?array $times, bool $subscribed = true): User
    {
        $user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true, 'nudge_times' => $times]);

        if ($subscribed) {
            PushSubscription::storeFor($user, 'https://push.example.com/'.$user->id, 'p256dh-key', 'auth-token', 'Mozilla/5.0');
        }

        return $user;
    }

    private function fakePush(?\Closure $onNotify = null): void
    {
        $this->sent = [];
        $mock = Mockery::mock(PushNotifier::class);
        $mock->shouldReceive('notify')->andReturnUsing(function (User $user, array $payload) use ($onNotify) {
            if ($onNotify) {
                $onNotify($user);
            }
            $this->sent[] = ['user' => $user->id, 'payload' => $payload];
        });
        $this->app->instance(PushNotifier::class, $mock);
    }

    private function at(string $time, string $date = '2026-11-09'): void
    {
        $this->travelTo(CarbonImmutable::parse("{$date} {$time}", 'Europe/Zurich'));
    }

    private function tick(): void
    {
        $this->artisan('app:send-evening-nudges')->assertSuccessful();
    }

    private function sentTo(User $user): int
    {
        return count(array_filter($this->sent, fn (array $s) => $s['user'] === $user->id));
    }

    public function test_the_nudge_is_sent_at_the_users_time_and_links_to_the_reset(): void
    {
        $this->at('18:29');
        $this->tick();
        $this->assertSame(0, $this->sentTo($this->user));

        $this->at('18:30');
        $this->tick();

        $this->assertSame(1, $this->sentTo($this->user));
        $this->assertSame('/board?reset=1', $this->sent[0]['payload']['url']);
        $this->assertSame('Zeit zum Abschalten', $this->sent[0]['payload']['title']);
        $this->assertNotNull(Evening::for($this->user, '2026-11-09')->nudge_sent_at);
    }

    public function test_at_most_one_nudge_per_evening(): void
    {
        $this->at('18:30');
        $this->tick();
        $this->at('18:31');
        $this->tick();
        $this->at('21:00');
        $this->tick();

        $this->assertSame(1, $this->sentTo($this->user));
    }

    public function test_the_next_evening_gets_its_own_nudge(): void
    {
        $this->at('18:30');
        $this->tick();
        $this->at('18:30', '2026-11-10');
        $this->tick();

        $this->assertSame(2, $this->sentTo($this->user));
    }

    public function test_a_late_scheduler_tick_still_sends_but_not_hours_later(): void
    {
        $this->at('19:45');
        $this->tick();
        $this->assertSame(1, $this->sentTo($this->user), 'a delayed cron still catches up');

        $other = $this->userWith(['1' => '18:30']);
        $this->at('20:31');
        $this->tick();
        $this->assertSame(0, $this->sentTo($other), 'two hours late is too late: nothing out of the blue');
    }

    public function test_no_nudge_on_a_weekday_without_a_time(): void
    {
        $this->at('18:30', '2026-11-14'); // Saturday has no time
        $this->tick();

        $this->assertSame(0, $this->sentTo($this->user));
    }

    public function test_no_nudge_for_a_user_who_never_set_a_time(): void
    {
        $never = $this->userWith(null);
        $this->at('18:30');
        $this->tick();

        $this->assertSame(0, $this->sentTo($never));
    }

    public function test_no_nudge_on_a_paused_day(): void
    {
        SchedulePause::forceCreate(['user_id' => $this->user->id, 'date' => '2026-11-09']);
        $this->at('18:30');
        $this->tick();

        $this->assertSame(0, $this->sentTo($this->user));
    }

    public function test_no_nudge_once_tonights_reset_was_started_or_closed(): void
    {
        $closed = $this->userWith(['1' => '18:30']);
        $this->at('18:00');
        app(EveningReset::class)->begin($this->user);
        app(EveningReset::class)->close($closed);

        $this->at('18:30');
        $this->tick();

        $this->assertSame(0, $this->sentTo($this->user), 'started by hand before the time');
        $this->assertSame(0, $this->sentTo($closed), 'skipped before the time');
    }

    public function test_no_nudge_during_a_running_session_but_it_comes_after(): void
    {
        $task = Task::forceCreate(['user_id' => $this->user->id, 'title' => 'Formular', 'list' => 'todos', 'size' => Size::Quick]);
        $this->at('18:20');
        app(FocusSession::class)->start($this->user, $task->id);

        $this->at('18:30');
        $this->tick();
        $this->assertSame(0, $this->sentTo($this->user), 'never into a focus block');

        app(FocusSession::class)->stop($this->user);
        $this->at('18:50');
        $this->tick();
        $this->assertSame(1, $this->sentTo($this->user));
    }

    public function test_no_subscription_means_nothing_sent_and_no_error(): void
    {
        $quiet = $this->userWith(['1' => '18:30'], subscribed: false);
        $this->at('18:30');
        $this->tick();

        $this->assertSame(0, $this->sentTo($quiet));
        $this->assertNull(Evening::for($quiet, '2026-11-09')?->nudge_sent_at);
    }

    public function test_a_failing_push_does_not_stop_the_others_and_is_not_retried_all_evening(): void
    {
        $second = $this->userWith(['1' => '18:30']);
        $this->fakePush(function (User $user) {
            if ($user->is($this->user)) {
                throw new \RuntimeException('push service down');
            }
        });

        $this->at('18:30');
        $this->tick();
        $this->tick();

        $this->assertSame(1, $this->sentTo($second));
        $this->assertNotNull(Evening::for($this->user, '2026-11-09')->nudge_sent_at, 'one try per evening, no hammering');
    }

    public function test_the_time_follows_local_time_across_the_october_dst_change(): void
    {
        // Sunday 25 October 2026: Zurich goes from UTC+2 to UTC+1 at 03:00.
        // The Saturday before is still summer time; the user wants 18:00 on Sundays.
        $sunday = $this->userWith(['6' => '18:00', '7' => '18:00']);

        $this->travelTo(CarbonImmutable::parse('2026-10-24 16:00', 'UTC')); // 18:00 CEST
        $this->tick();
        $this->assertSame(1, $this->sentTo($sunday), 'Saturday 18:00 summer time');

        $this->travelTo(CarbonImmutable::parse('2026-10-25 16:00', 'UTC')); // 17:00 CET: one hour early
        $this->tick();
        $this->assertSame(1, $this->sentTo($sunday));

        $this->travelTo(CarbonImmutable::parse('2026-10-25 17:00', 'UTC')); // 18:00 CET
        $this->tick();
        $this->assertSame(2, $this->sentTo($sunday), 'Sunday 18:00 winter time');
    }

    public function test_the_time_follows_local_time_across_the_march_dst_change(): void
    {
        // Sunday 28 March 2027: Zurich goes from UTC+1 to UTC+2 at 02:00.
        $sunday = $this->userWith(['7' => '18:00']);

        $this->travelTo(CarbonImmutable::parse('2027-03-28 16:00', 'UTC')); // 18:00 CEST
        $this->tick();

        $this->assertSame(1, $this->sentTo($sunday));
        $this->assertNotNull(Evening::for($sunday, '2027-03-28')->nudge_sent_at);
    }

    public function test_the_command_is_scheduled_every_minute(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('app:send-evening-nudges')->assertSuccessful();
    }

    public function test_times_are_read_defensively(): void
    {
        $odd = $this->userWith(['1' => '25:99', '2' => 'abc', '8' => '18:00', '3' => '07:05']);

        $this->assertSame([3 => '07:05'], EveningNudge::times($odd));
    }
}
