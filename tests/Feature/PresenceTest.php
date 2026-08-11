<?php

namespace Tests\Feature;

use App\Livewire\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class PresenceTest extends TestCase
{
    use RefreshDatabase;

    // ── Heartbeat endpoint ────────────────────────────────────────────

    public function test_the_heartbeat_records_the_current_time(): void
    {
        $user = User::factory()->create(['last_seen_at' => null]);

        $this->actingAs($user)
            ->post(route('presence.heartbeat'))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertNotNull($user->fresh()->last_seen_at);
    }

    public function test_the_heartbeat_requires_a_signed_in_user(): void
    {
        $this->post(route('presence.heartbeat'))->assertRedirect(route('login'));
    }

    public function test_the_heartbeat_does_not_bump_updated_at(): void
    {
        // A heartbeat is not a change to the account — it fires once a minute
        // per open tab and must not look like the user edited something.
        $user = User::factory()->create(['last_seen_at' => null]);
        $originalUpdatedAt = $user->updated_at;

        $this->travel(5)->minutes();
        $this->actingAs($user)->post(route('presence.heartbeat'))->assertOk();

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->last_seen_at);
        $this->assertEquals($originalUpdatedAt->timestamp, $fresh->updated_at->timestamp);
    }

    public function test_the_heartbeat_records_nothing_when_presence_is_off(): void
    {
        $user = User::factory()->create(['show_presence' => false, 'last_seen_at' => null]);

        $this->actingAs($user)->post(route('presence.heartbeat'))->assertOk();

        // Opting out stops the recording, not just the display.
        $this->assertNull($user->fresh()->last_seen_at);
    }

    // ── isOnline / lastSeenLabel ──────────────────────────────────────

    public function test_a_recent_heartbeat_counts_as_online(): void
    {
        $user = User::factory()->create(['last_seen_at' => Carbon::now()->subSeconds(30)]);

        $this->assertTrue($user->isOnline());
    }

    public function test_presence_expires_after_the_ttl(): void
    {
        $justInside = User::factory()->create([
            'last_seen_at' => Carbon::now()->subSeconds(User::PRESENCE_TTL_SECONDS - 10),
        ]);
        $justOutside = User::factory()->create([
            'last_seen_at' => Carbon::now()->subSeconds(User::PRESENCE_TTL_SECONDS + 10),
        ]);

        $this->assertTrue($justInside->isOnline(), 'One missed beat must not flick someone offline.');
        $this->assertFalse($justOutside->isOnline());
    }

    public function test_someone_who_has_never_been_seen_is_offline(): void
    {
        $this->assertFalse(User::factory()->create(['last_seen_at' => null])->isOnline());
        $this->assertNull(User::factory()->create(['last_seen_at' => null])->lastSeenLabel());
    }

    public function test_presence_is_hidden_entirely_when_opted_out(): void
    {
        $user = User::factory()->create([
            'show_presence' => false,
            'last_seen_at' => Carbon::now(),
        ]);

        $this->assertFalse($user->isOnline());
        $this->assertNull($user->lastSeenLabel());
    }

    public function test_the_last_seen_label_buckets_by_age(): void
    {
        $cases = [
            [20, 'gerade eben'],
            [60 * 5, 'vor 5 Min'],
            [3600 * 3, 'vor 3 Std'],
            [86400 * 2, 'vor 2 Tagen'],
        ];

        foreach ($cases as [$secondsAgo, $expected]) {
            $user = User::factory()->create(['last_seen_at' => Carbon::now()->subSeconds($secondsAgo)]);
            $this->assertSame($expected, $user->lastSeenLabel(), "{$secondsAgo}s ago");
        }
    }

    // ── Setting ───────────────────────────────────────────────────────

    public function test_presence_is_on_by_default(): void
    {
        // An empty presence list would read as a broken feature, and only
        // classmates in a space you chose to join can see it.
        $this->assertTrue(User::factory()->create()->fresh()->show_presence);
    }

    public function test_turning_presence_off_clears_the_recorded_timestamp(): void
    {
        $user = User::factory()->create(['last_seen_at' => Carbon::now()]);

        Livewire::actingAs($user)->test(Settings::class)->call('toggleShowPresence');

        $user->refresh();
        $this->assertFalse($user->show_presence);
        $this->assertNull($user->last_seen_at);
    }
}
