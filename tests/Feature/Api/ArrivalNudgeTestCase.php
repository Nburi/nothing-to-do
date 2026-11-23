<?php

namespace Tests\Feature\Api;

use App\Models\PushSubscription;
use App\Models\User;
use App\Services\PushNotifier;
use App\Support\NarrowAbility;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\TestCase;

/**
 * Shared set-up of the arrival rule tests (A71 to A73, R2): a user with evening nudge times, one
 * push device, a narrow arrival token, a fake push service that records what goes out through
 * either path (the scheduler's notify() or the arrival's sendDebug()), and local time in
 * Europe/Zurich. Monday 9 November 2026 (nudge time 18:30) unless a test says otherwise.
 */
abstract class ArrivalNudgeTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    /** @var list<array{user: int, via: string, payload: array<string, mixed>}> */
    protected array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('de');

        $this->user = User::factory()->create([
            'timezone_offset' => 1,
            'timezone_auto_dst' => true,
            'nudge_times' => ['1' => '18:30', '2' => '18:30', '3' => '18:30', '4' => '18:30', '5' => '18:30', '7' => '18:00'],
        ]);
        PushSubscription::storeFor($this->user, 'https://push.example.com/'.$this->user->id, 'p256dh-key', 'auth-token', 'Mozilla/5.0');
        $this->token = $this->user->createToken('iPhone', [NarrowAbility::ARRIVAL])->plainTextToken;
        $this->fakePush();
    }

    protected function fakePush(): void
    {
        $this->sent = [];
        $mock = Mockery::mock(PushNotifier::class);
        $mock->shouldReceive('notify')->andReturnUsing(function (User $user, array $payload) {
            $this->sent[] = ['user' => $user->id, 'via' => 'nudge', 'payload' => $payload];
        });
        $mock->shouldReceive('sendDebug')->andReturnUsing(function (User $user, array $payload) {
            $this->sent[] = ['user' => $user->id, 'via' => 'arrival', 'payload' => $payload];

            return [['endpoint' => 'https://push.example.com/'.$user->id, 'user_agent' => null, 'success' => true, 'status' => 201, 'reason' => 'OK']];
        });
        $this->app->instance(PushNotifier::class, $mock);
    }

    protected function at(string $time, string $date = '2026-11-09'): void
    {
        $this->travelTo(CarbonImmutable::parse("{$date} {$time}", 'Europe/Zurich'));
    }

    /** The phone reports an arrival. */
    protected function arrive(): TestResponse
    {
        // A fresh user per call: a test may change the account between two arrivals.
        $this->app['auth']->forgetGuards();

        return $this->withToken($this->token)->postJson('/api/arrival');
    }

    /** The scheduler's tick: the time-based nudge. */
    protected function tick(): void
    {
        $this->artisan('app:send-evening-nudges')->assertSuccessful();
    }

    protected function pushes(): int
    {
        return count($this->sent);
    }
}
