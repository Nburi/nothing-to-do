<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The mobile header redesign: the wordmark hides below sm, the standalone
 * "Mehr" trigger becomes desktop-only, and its links fold into a mobile-only
 * section inside the avatar menu instead — see layouts/app.blade.php and
 * partials/mehr-nav-links.blade.php. These are structural regression guards
 * for that split, not a re-test of HeaderBadges/AppModules logic itself
 * (already covered by HeaderBadgesTest/ModuleNavVisibilityTest/etc.).
 */
class MobileHeaderMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_wordmark_is_hidden_below_sm_and_shown_from_sm_up(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('app'));

        $response->assertSee('hidden text-[15px] font-medium tracking-tight sm:inline', false);
    }

    public function test_the_standalone_mehr_trigger_is_desktop_only(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('app'));

        $response->assertSee('relative hidden sm:block', false);
    }

    /**
     * Regression guard for a real bug caught in review: the per-link stagger
     * delay was originally built with a `fn ()` arrow function, which
     * captures $mehrLinkIndex by value — the ++ inside it only ever mutated
     * the closure's own copy, so every link would have rendered with the
     * exact same "animation-delay: 0ms" instead of fanning in staggered.
     * The fix uses a regular closure with `use (&$mehrLinkIndex)`.
     */
    public function test_the_staggered_funktionen_links_get_increasing_animation_delays(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('app'));

        $response->assertSee('style="animation-delay: 0ms"', false);
        $response->assertSee('style="animation-delay: 35ms"', false);
    }

    public function test_the_avatar_menu_carries_a_mobile_only_funktionen_section_when_modules_are_visible(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('app'));

        $response->assertSee('Weitere Funktionen');
        $response->assertSee('border-b border-line py-1 sm:hidden', false);
        // The stagger flourish only applies to the mobile-folded copy of the
        // links, not the desktop panel's own (instant) copy.
        $response->assertSee('header-menu-fan-in', false);
    }

    public function test_the_avatar_menus_funktionen_section_disappears_with_every_module_hidden(): void
    {
        $user = User::factory()->create(['hidden_modules' => [
            'prepare', 'schedule', 'weekplan', 'agenda', 'crafts', 'emergency',
        ]]);

        $response = $this->actingAs($user)->get(route('app'));

        $response->assertDontSee('Weitere Funktionen');
        $response->assertDontSee('header-menu-fan-in', false);
    }

    public function test_the_emergency_dot_moves_to_the_avatar_button_on_mobile_while_active(): void
    {
        $user = User::factory()->create();
        $project = \App\Models\Project::factory()->for($user)->create();
        $user->update(['emergency_project_id' => $project->id]);

        $response = $this->actingAs($user)->get(route('app'));

        $response->assertSee('absolute -right-0.5 -top-0.5 h-2 w-2 rounded-full bg-signal ring-2 ring-paper sm:hidden', false);
    }

    public function test_the_emergency_dot_is_absent_from_the_avatar_button_when_not_in_emergency_mode(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('app'));

        $response->assertDontSee('ring-2 ring-paper sm:hidden', false);
    }
}
