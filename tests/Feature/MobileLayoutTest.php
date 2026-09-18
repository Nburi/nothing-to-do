<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards for the mobile-polish pass (see MOBILE_IMPROVEMENTS.md). Layout can't be measured in
 * PHPUnit, so these pin the markup/CSS decisions that fixed measured problems at 375px.
 */
class MobileLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_planner_action_group_is_not_flex_none_on_mobile(): void
    {
        // A `flex-none flex-wrap` group never wraps, which made the whole Planer page scroll
        // sideways on a phone (scrollWidth 520 vs a 375 viewport).
        $user = User::factory()->create(['planner_enabled' => true]);

        $html = $this->actingAs($user)->get(route('planner'))->assertOk()->getContent();

        $this->assertStringContainsString('flex max-w-full flex-wrap items-center gap-2 sm:flex-none', $html);
        $this->assertStringNotContainsString('flex flex-none flex-wrap items-center gap-2', $html);
    }

    public function test_settings_category_form_lets_the_name_field_wrap_onto_its_own_row(): void
    {
        // The name input used to be squeezed to ~26px between the swatches and the button.
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('settings'))->assertOk()->getContent();

        $this->assertStringContainsString('basis-full', $html);
        $this->assertStringContainsString('sm:flex-nowrap', $html);
    }

    public function test_settings_toggles_carry_an_extended_hit_area(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get(route('settings'))->assertOk()->getContent();

        $this->assertStringContainsString('relative hit-area h-6 w-10', $html);
    }

    public function test_stylesheet_declares_the_hit_area_utility_and_the_16px_input_rule(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('.hit-area::before', $css);
        // iOS Safari zooms on focus for anything under 16px.
        $this->assertMatchesRegularExpression('/@media \(max-width: 639px\) \{\s*input:not[^{]+\{\s*font-size: 16px;/', $css);
    }

    public function test_mobile_task_card_action_buttons_are_at_least_36px(): void
    {
        $view = file_get_contents(resource_path('views/livewire/partials/task-card-mobile.blade.php'));

        $this->assertStringNotContainsString('h-7 w-7', $view);
        $this->assertStringContainsString('hit-area mt-px grid h-[22px] w-[22px]', $view);
    }
}
