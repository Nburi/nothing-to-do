<?php

namespace Tests\Feature;

use App\Livewire\Settings;
use App\Models\User;
use App\Services\HeaderBadges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsHeaderBadgesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_never_customised_user_sees_the_default_rows_in_settings(): void
    {
        $user = User::factory()->create();

        $rows = Livewire::actingAs($user)->test(Settings::class)->get('headerBadgeRows');

        $this->assertTrue($rows->firstWhere('key', 'streak')['enabled']);
        $this->assertTrue($rows->firstWhere('key', 'agenda')['enabled']);
        $this->assertFalse($rows->firstWhere('key', 'today')['enabled']);
    }

    public function test_toggle_header_badge_flips_and_persists_the_enabled_flag(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Settings::class)->call('toggleHeaderBadge', 'today');

        $rows = collect($user->fresh()->header_badges)->keyBy('key');
        $this->assertTrue($rows['today']['enabled']);

        // Toggling back off persists too, not just the first flip.
        Livewire::actingAs($user)->test(Settings::class)->call('toggleHeaderBadge', 'today');
        $rows = collect($user->fresh()->header_badges)->keyBy('key');
        $this->assertFalse($rows['today']['enabled']);
    }

    public function test_toggling_one_badge_never_disturbs_another_badges_flag(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Settings::class)->call('toggleHeaderBadge', 'today');

        $rows = collect($user->fresh()->header_badges)->keyBy('key');
        // 'agenda' was on by default and must still be on.
        $this->assertTrue($rows['agenda']['enabled']);
    }

    public function test_reorder_header_badges_persists_the_new_order_and_keeps_enabled_flags(): void
    {
        $user = User::factory()->create();
        $newOrder = ['emergency', 'goal', 'schedule', 'today', 'agenda', 'streak'];

        Livewire::actingAs($user)->test(Settings::class)->call('reorderHeaderBadges', $newOrder);

        $stored = collect($user->fresh()->header_badges);
        $this->assertSame($newOrder, $stored->pluck('key')->all());
        $this->assertTrue($stored->firstWhere('key', 'agenda')['enabled']);
        $this->assertTrue($stored->firstWhere('key', 'streak')['enabled']);
        $this->assertFalse($stored->firstWhere('key', 'today')['enabled']);
    }

    public function test_reordering_with_a_stray_unknown_key_ignores_it(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Settings::class)
            ->call('reorderHeaderBadges', ['streak', 'not-a-real-badge', 'agenda', 'today', 'schedule', 'goal', 'emergency']);

        $keys = collect($user->fresh()->header_badges)->pluck('key');
        $this->assertNotContains('not-a-real-badge', $keys);
        $this->assertSame(array_keys(HeaderBadges::CATALOG), $keys->all());
    }

    public function test_the_header_badges_card_renders_in_the_general_tab(): void
    {
        $user = User::factory()->create();

        $html = Livewire::actingAs($user)->test(Settings::class)->html();

        $this->assertStringContainsString('Header-Badges', $html);
    }
}
