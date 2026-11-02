<?php

namespace Tests\Unit;

use App\Models\CraftIdea;
use App\Models\User;
use App\Services\HeaderBadges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderBadgesModuleVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_hidden_modules_badge_is_dropped_even_when_it_has_content(): void
    {
        $user = User::factory()->create(['header_badges' => [['key' => 'crafts', 'enabled' => true]]]);
        CraftIdea::factory()->for($user)->create();

        $this->assertTrue(collect(HeaderBadges::visibleFor($user))->contains('key', 'crafts'));

        $user->update(['hidden_modules' => ['crafts']]);

        $this->assertFalse(collect(HeaderBadges::visibleFor($user->refresh()))->contains('key', 'crafts'));
    }

    public function test_a_hidden_crafts_module_drops_the_crafts_badge_even_when_it_has_content(): void
    {
        // Opt-in badge (not in HeaderBadges::DEFAULT_ENABLED), so it must be
        // explicitly enabled here — otherwise "not shown" would be
        // indistinguishable from the module-hiding this test is about.
        $user = User::factory()->create([
            'hidden_modules' => ['crafts'],
            'header_badges' => [['key' => 'crafts', 'enabled' => true]],
        ]);
        CraftIdea::factory()->for($user)->create();

        $badges = collect(HeaderBadges::visibleFor($user));

        $this->assertFalse($badges->contains('key', 'crafts'));
    }
}
