<?php

namespace Tests\Feature\Refactor;

use App\Models\CrunchAlert;
use App\Models\EventCategory;
use App\Models\EventTemplate;
use App\Models\PushSubscription;
use App\Models\Task;
use App\Models\User;
use App\Planning\Size;
use App\Services\FocusSession;
use App\Services\Planning\CrunchDecisions;
use App\Services\PushNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

/**
 * A30 / A31 (R5, risk F7; refactor step 4.2): the early warning is pushed.
 *
 * Deep work (pomodoro) Mon–Fri 19:30–21:30 = 4 sessions an evening. Monday 9 November 2026.
 * "Bericht" (hard, Wednesday 11.11.) needs 350 min, Monday to Wednesday hold 300: 2 sessions
 * are missing. The gate of phase 4: the crunch shows up two days before it bites.
 */
class CrunchPushTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /** @var list<array{user: int, payload: array}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');
        $this->user = $this->subscribedUser();
        $this->fakePush();
    }

    private function subscribedUser(bool $subscribed = true): User
    {
        $user = User::factory()->create(['timezone_offset' => 1, 'timezone_auto_dst' => true]);
        $deep = EventCategory::factory()->for($user)->pomodoro()->create();
        EventTemplate::factory()->for($user)->create([
            'category_id' => $deep->id, 'is_recurring' => true, 'recurrence' => '1,2,3,4,5',
            'default_start' => '19:30', 'duration' => 120,
        ]);
        if ($subscribed) {
            PushSubscription::storeFor($user, 'https://push.example.com/'.$user->id, 'p256dh-key', 'auth-token', 'Mozilla/5.0');
        }

        return $user;
    }

    private function crunch(?User $user = null, array $attributes = []): Task
    {
        return Task::factory()->for($user ?? $this->user)->create([
            'title' => 'Bericht', 'list' => 'tasks', 'size' => Size::Block, 'duration_minutes' => 350,
            'hard_deadline' => '2026-11-11', ...$attributes,
        ]);
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

    private function checks(): void
    {
        $this->artisan('app:run-planning-checks')->assertSuccessful();
    }

    private function sentTo(User $user): array
    {
        return array_values(array_filter($this->sent, fn (array $s) => $s['user'] === $user->id));
    }

    public function test_a_crunch_two_days_ahead_is_pushed_once_with_the_crunch_screen_link(): void
    {
        $this->crunch();
        $this->at('09:00');
        $this->checks();
        $this->at('10:00');
        $this->checks();

        $sent = $this->sentTo($this->user);
        $this->assertCount(1, $sent, 'at most one push a day');
        $this->assertSame('Engpass am Mittwoch', $sent[0]['payload']['title']);
        $this->assertSame('2 Sessions fehlen bis «Bericht». Schau kurz drauf, du entscheidest.', $sent[0]['payload']['body']);
        $this->assertSame('/board?crunch=1', $sent[0]['payload']['url']);
        $this->assertNotNull(CrunchAlert::sole()->pushed_at);
        $this->assertNull(CrunchAlert::sole()->push_pending_since);
    }

    public function test_the_gate_the_crunch_shows_on_the_board_two_days_before_it_bites(): void
    {
        $this->crunch();
        $this->at('09:00');

        $this->actingAs($this->user)->get('/board')->assertOk()
            ->assertSee('Engpass bis Mittwoch: 2 Sessions fehlen')
            ->assertSee('data-action="crunch"', false);
        $this->assertTrue(CrunchAlert::sole()->active);
    }

    public function test_at_night_the_push_waits_for_eight_in_the_morning(): void
    {
        $this->crunch();
        $this->at('22:30');
        $this->checks();
        $this->at('07:00', '2026-11-10');
        $this->checks();
        $this->assertCount(0, $this->sentTo($this->user), 'never at night');

        $this->at('08:00', '2026-11-10');
        $this->checks();
        $this->assertCount(1, $this->sentTo($this->user));
        $this->assertSame('Engpass morgen', $this->sentTo($this->user)[0]['payload']['title']);
    }

    public function test_no_push_into_a_running_session_it_comes_after(): void
    {
        $task = $this->crunch();
        $this->at('09:00');
        app(FocusSession::class)->start($this->user, $task->id);
        $this->checks();
        $this->assertCount(0, $this->sentTo($this->user));

        app(FocusSession::class)->stop($this->user);
        $this->at('10:00');
        $this->checks();
        $this->assertCount(1, $this->sentTo($this->user));
    }

    public function test_a_soft_only_crunch_is_never_pushed(): void
    {
        $this->crunch(attributes: ['hard_deadline' => null, 'soft_deadline' => '2026-11-11']);
        $this->at('09:00');
        $this->checks();

        $this->assertCount(0, $this->sentTo($this->user));
        $this->assertTrue(CrunchAlert::sole()->active, 'active but quiet (F5)');
        $this->assertNull(CrunchAlert::sole()->push_pending_since);
    }

    public function test_seen_on_the_board_first_means_no_push(): void
    {
        $this->crunch();
        $this->at('09:00');
        $this->actingAs($this->user)->get('/board')->assertOk();
        $this->at('09:30');
        $this->checks();

        $this->assertCount(0, $this->sentTo($this->user));
        $this->assertTrue(CrunchAlert::sole()->active);
    }

    public function test_a_crunch_solved_before_the_morning_is_not_pushed(): void
    {
        $task = $this->crunch();
        $this->at('23:00');
        $this->checks();
        (new CrunchDecisions)->drop($this->user, $task->id);

        $this->at('08:00', '2026-11-10');
        $this->checks();

        $this->assertCount(0, $this->sentTo($this->user));
        $this->assertNull(CrunchAlert::sole()->push_pending_since);
    }

    public function test_without_a_subscription_nothing_is_sent_and_nothing_fails(): void
    {
        $quiet = $this->subscribedUser(subscribed: false);
        $this->crunch($quiet);
        $this->at('09:00');
        $this->checks();

        $this->assertCount(0, $this->sentTo($quiet));
        $this->assertNull(CrunchAlert::query()->where('user_id', $quiet->id)->sole()->push_pending_since);
    }

    public function test_a_failing_push_does_not_stop_the_others_and_is_not_retried_today(): void
    {
        $second = $this->subscribedUser();
        $this->crunch();
        $this->crunch($second);
        $this->fakePush(function (User $user) {
            if ($user->is($this->user)) {
                throw new \RuntimeException('push service down');
            }
        });

        $this->at('09:00');
        $this->checks();
        $this->at('10:00');
        $this->checks();

        $this->assertCount(1, $this->sentTo($second));
        $this->assertNotNull(CrunchAlert::query()->where('user_id', $this->user->id)->sole()->pushed_at, 'one try a day');
    }

    public function test_a_server_without_vapid_keys_runs_the_checks_without_errors(): void
    {
        Log::spy();
        $this->app->bind(PushNotifier::class, fn () => throw new \ErrorException('[VAPID] Public key should be 65 bytes long when decoded.'));
        $this->crunch();
        $this->at('09:00');

        $this->artisan('app:run-planning-checks')->assertSuccessful();

        $this->assertTrue(CrunchAlert::sole()->active);
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => $message === 'Crunch warning could not be sent')->once();
    }
}
