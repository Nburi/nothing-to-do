<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultLandingPageRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_to_the_board_by_default(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertRedirect(route('app'));
    }

    public function test_root_redirects_to_the_chosen_default_page(): void
    {
        $user = User::factory()->create(['default_page' => 'agenda']);

        $this->actingAs($user)->get('/')->assertRedirect(route('agenda'));
    }

    public function test_root_self_heals_to_the_board_once_the_default_page_is_hidden(): void
    {
        $user = User::factory()->create(['default_page' => 'agenda', 'hidden_modules' => ['agenda']]);

        $this->actingAs($user)->get('/')->assertRedirect(route('app'));
    }

    public function test_a_guest_still_sees_the_welcome_page(): void
    {
        $this->get('/')->assertOk()->assertViewIs('welcome');
    }

    public function test_dashboard_redirects_to_the_chosen_default_page(): void
    {
        $user = User::factory()->create(['default_page' => 'schedule']);

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('schedule'));
    }

    public function test_dashboard_sends_a_guest_to_the_board_which_then_bounces_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('app'));
    }
}
