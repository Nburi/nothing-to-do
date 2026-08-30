<?php

namespace Tests\Unit;

use App\Models\AgendaEntry;
use App\Models\CraftIdea;
use App\Models\Project;
use App\Models\User;
use App\Services\HeaderBadges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderBadgesModuleVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_hidden_modules_badge_is_dropped_even_when_it_has_content(): void
    {
        $user = User::factory()->create(['hidden_modules' => ['agenda']]);
        AgendaEntry::factory()->for($user)->create(['type' => 'homework', 'date' => now()->addDay()]);

        $badges = collect(HeaderBadges::visibleFor($user));

        $this->assertFalse($badges->contains('key', 'agenda'));
    }

    public function test_the_emergency_badge_still_shows_while_an_emergency_is_active_even_if_hidden(): void
    {
        // The emergency badge is opt-in (not in HeaderBadges::DEFAULT_ENABLED),
        // so it must be explicitly enabled here — otherwise the "not shown"
        // outcome would be indistinguishable from the module-hiding this test
        // is actually about.
        $user = User::factory()->create([
            'hidden_modules' => ['emergency'],
            'header_badges' => [['key' => 'emergency', 'enabled' => true]],
        ]);
        $project = Project::factory()->for($user)->create();
        $user->update(['emergency_project_id' => $project->id]);

        $badges = collect(HeaderBadges::visibleFor($user));

        $this->assertTrue($badges->contains('key', 'emergency'));
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
