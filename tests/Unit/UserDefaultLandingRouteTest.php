<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDefaultLandingRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_to_the_board(): void
    {
        $user = User::factory()->create();

        $this->assertSame('app', $user->defaultLandingRouteName());
    }

    public function test_resolves_a_visible_module_to_its_route(): void
    {
        $user = User::factory()->create(['default_page' => 'agenda']);

        $this->assertSame('agenda', $user->defaultLandingRouteName());
    }

    public function test_falls_back_to_the_board_once_the_chosen_module_is_hidden(): void
    {
        $user = User::factory()->create(['default_page' => 'agenda', 'hidden_modules' => ['agenda']]);

        $this->assertSame('app', $user->defaultLandingRouteName());
    }

    public function test_falls_back_to_the_board_for_a_garbage_value(): void
    {
        $user = User::factory()->create(['default_page' => 'not-a-real-module']);

        $this->assertSame('app', $user->defaultLandingRouteName());
    }
}
