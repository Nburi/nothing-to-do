<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\AppModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_catalog_module_is_visible_for_a_user_who_never_touched_settings(): void
    {
        $user = User::factory()->create();

        foreach (array_keys(AppModules::CATALOG) as $key) {
            $this->assertTrue(AppModules::isVisible($user, $key));
        }
    }

    public function test_an_unknown_key_is_always_visible(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(AppModules::isVisible($user, 'not-a-real-module'));
    }

    public function test_a_hidden_module_is_not_visible(): void
    {
        $user = User::factory()->create(['hidden_modules' => ['agenda']]);

        $this->assertFalse(AppModules::isVisible($user, 'agenda'));
        $this->assertTrue(AppModules::isVisible($user, 'schedule'));
    }

    public function test_rows_for_reflects_hidden_state_for_every_catalog_key(): void
    {
        $user = User::factory()->create(['hidden_modules' => ['crafts', 'progress']]);

        $rows = collect(AppModules::rowsFor($user))->keyBy('key');

        $this->assertSame(count(AppModules::CATALOG), $rows->count());
        $this->assertTrue($rows['crafts']['hidden']);
        $this->assertTrue($rows['progress']['hidden']);
        $this->assertFalse($rows['agenda']['hidden']);
    }

    public function test_landing_page_options_always_start_with_the_board_and_exclude_hidden_modules(): void
    {
        $user = User::factory()->create(['hidden_modules' => ['agenda']]);

        $options = collect(AppModules::landingPageOptions($user));

        $this->assertSame('app', $options->first()['key']);
        $this->assertFalse($options->contains('key', 'agenda'));
        $this->assertTrue($options->contains('key', 'schedule'));
    }

    public function test_is_valid_landing_page(): void
    {
        $user = User::factory()->create(['hidden_modules' => ['agenda']]);

        $this->assertTrue(AppModules::isValidLandingPage($user, 'app'));
        $this->assertTrue(AppModules::isValidLandingPage($user, 'schedule'));
        $this->assertFalse(AppModules::isValidLandingPage($user, 'agenda'));
        $this->assertFalse(AppModules::isValidLandingPage($user, 'not-a-real-module'));
    }
}
