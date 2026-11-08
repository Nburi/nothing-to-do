<?php

namespace Tests\Feature\Api;

use App\Livewire\Settings;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\PushNotifier;
use App\Support\NarrowAbility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Spike 0.1 (R2, risks F1, C1, C10): an iPhone Shortcut on home Wi-Fi calls
 * POST /api/arrival with a narrow token; the server sends a Web Push.
 */
class ArrivalTriggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_needs_a_token(): void
    {
        $this->postJson('/api/arrival')->assertUnauthorized();
    }

    public function test_a_token_without_the_arrival_ability_is_refused(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mcp', ['mcp:read'])->plainTextToken;
        $this->mock(PushNotifier::class, fn (MockInterface $m) => $m->shouldNotReceive('sendDebug'));

        $this->withToken($token)->postJson('/api/arrival')
            ->assertForbidden()
            ->assertJsonPath('message', 'Dieses Token darf keine Ankunft melden.');
    }

    public function test_an_arrival_sends_a_push_to_every_device(): void
    {
        $user = User::factory()->create();
        PushSubscription::storeFor($user, 'https://push.example/a', 'key-a', 'auth-a', 'iPhone');
        PushSubscription::storeFor($user, 'https://push.example/b', 'key-b', 'auth-b', 'Mac');
        $token = $user->createToken('iPhone', [NarrowAbility::ARRIVAL])->plainTextToken;

        $this->mock(PushNotifier::class, function (MockInterface $m) use ($user) {
            $m->shouldReceive('sendDebug')->once()
                ->withArgs(fn (User $u, array $payload) => $u->is($user)
                    && $payload['title'] === 'Du bist zuhause'
                    && $payload['url'] === '/')
                ->andReturn([
                    ['endpoint' => 'a', 'user_agent' => null, 'success' => true, 'status' => 201, 'reason' => 'OK'],
                    ['endpoint' => 'b', 'user_agent' => null, 'success' => false, 'status' => 400, 'reason' => 'Bad'],
                ]);
        });

        $this->withToken($token)->postJson('/api/arrival')
            ->assertOk()
            ->assertJson(['devices' => 2, 'delivered' => 1]);
    }

    public function test_without_a_push_subscription_it_explains_what_to_do(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('iPhone', [NarrowAbility::ARRIVAL])->plainTextToken;
        $this->mock(PushNotifier::class, fn (MockInterface $m) => $m->shouldNotReceive('sendDebug'));

        $this->withToken($token)->postJson('/api/arrival')
            ->assertStatus(409)
            ->assertJsonPath('devices', 0)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'Mitteilungen'));
    }

    public function test_it_is_rate_limited(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('iPhone', [NarrowAbility::ARRIVAL])->plainTextToken;

        for ($i = 0; $i < 6; $i++) {
            $this->withToken($token)->postJson('/api/arrival')->assertStatus(409);
        }

        $this->withToken($token)->postJson('/api/arrival')->assertStatus(429);
    }

    public function test_an_arrival_token_cannot_read_or_change_anything_else(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('iPhone', [NarrowAbility::ARRIVAL])->plainTextToken;

        $this->withToken($token)->getJson('/api/tasks')->assertForbidden();
        $this->withToken($token)->getJson('/api/me')->assertForbidden();
        $this->withToken($token)->postJson('/api/tasks', ['title' => 'x'])->assertForbidden();
        $this->withToken($token)->postJson('/api/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertForbidden();
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_existing_tokens_keep_working_on_the_rest_api(): void
    {
        $user = User::factory()->create();

        $wildcard = $user->createToken('old')->plainTextToken;
        $mcp = $user->createToken('mcp', ['mcp:read'])->plainTextToken;

        $this->withToken($wildcard)->getJson('/api/me')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($mcp)->getJson('/api/me')->assertOk();
    }

    public function test_the_command_creates_a_token_with_only_the_arrival_ability(): void
    {
        $user = User::factory()->create(['email' => 'niels@example.test']);

        $this->artisan('app:arrival-token', ['email' => 'niels@example.test'])
            ->expectsOutputToContain('Token')
            ->assertSuccessful();

        $token = $user->tokens()->sole();
        $this->assertSame([NarrowAbility::ARRIVAL], $token->abilities);
        $this->assertSame('iPhone: Ankunft zuhause', $token->name);
    }

    public function test_settings_label_the_token_as_arrival_only_not_as_read_access(): void
    {
        $user = User::factory()->create();
        $user->createToken('iPhone: Ankunft zuhause', [NarrowAbility::ARRIVAL]);

        Livewire::actingAs($user)->test(Settings::class)
            ->assertSee('iPhone: Ankunft zuhause')
            ->assertSee('Nur Ankunft melden');
    }

    public function test_the_command_fails_calmly_for_an_unknown_email(): void
    {
        $this->artisan('app:arrival-token', ['email' => 'nobody@example.test'])
            ->expectsOutputToContain('Kein Konto')
            ->assertFailed();
    }
}
