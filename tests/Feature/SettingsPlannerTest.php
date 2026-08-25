<?php

namespace Tests\Feature;

use App\Livewire\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsPlannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_planner_defaults_to_disabled(): void
    {
        // A freshly-created (not reloaded) model doesn't have a DB-level
        // default(false) column in its in-memory attribute bag yet, so this
        // reads null rather than false here — the same fresh-model gotcha
        // documented in CLAUDE.md for every other default(false) column in
        // this app. null is falsy everywhere it's actually checked
        // (`! $user->planner_enabled`, `@if($user->planner_enabled)`), which
        // is exactly why it's deliberately not mirrored in User::$attributes.
        $user = User::factory()->create();

        $this->assertFalse((bool) $user->planner_enabled);
        $this->assertFalse($user->fresh()->planner_enabled);
    }

    public function test_toggling_planner_enabled_persists_and_updates_the_component(): void
    {
        $user = User::factory()->create(['planner_enabled' => false]);
        $this->actingAs($user);

        $component = Livewire::test(Settings::class)->call('togglePlannerEnabled');

        $component->assertSet('plannerEnabled', true);
        $this->assertTrue($user->fresh()->planner_enabled);
    }

    public function test_the_nav_pill_only_appears_once_enabled(): void
    {
        $user = User::factory()->create(['planner_enabled' => false]);
        $this->actingAs($user);

        $this->get(route('app'))->assertDontSee('Planer');

        $user->update(['planner_enabled' => true]);

        $this->get(route('app'))->assertSee('Planer');
    }
}
