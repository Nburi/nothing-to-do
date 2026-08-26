<?php

namespace Tests\Feature;

use App\Livewire\Onboarding;
use App\Livewire\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tutorial_page_renders_for_an_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('onboarding'))
            ->assertOk()
            ->assertSee('Willkommen bei nothing-to-do')
            ->assertSee('Die 3 Dinge');
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('onboarding'))->assertRedirect(route('login'));
    }

    public function test_a_brand_new_account_needs_onboarding(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->needsOnboarding());
    }

    public function test_finishing_stamps_onboarding_completed_and_redirects_to_the_default_page(): void
    {
        $user = User::factory()->create(['default_page' => 'agenda']);

        Livewire::actingAs($user)->test(Onboarding::class)
            ->call('finish')
            ->assertRedirect(route('agenda'));

        $fresh = $user->fresh();
        $this->assertFalse($fresh->needsOnboarding());
        $this->assertNotNull($fresh->onboarding_completed_at);
    }

    public function test_skipping_also_stamps_onboarding_completed(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Onboarding::class)
            ->call('skip')
            ->assertRedirect(route('app'));

        $this->assertFalse($user->fresh()->needsOnboarding());
    }

    public function test_a_user_who_already_finished_can_replay_it_and_the_timestamp_moves_forward(): void
    {
        $user = User::factory()->create(['onboarding_completed_at' => now()->subDays(10)]);
        $firstSeenAt = $user->onboarding_completed_at;

        Livewire::actingAs($user)->test(Onboarding::class)->call('finish');

        $this->assertTrue($user->fresh()->onboarding_completed_at->gt($firstSeenAt));
    }

    public function test_toggling_a_module_from_inside_the_tutorial_persists_like_settings(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Onboarding::class)->call('toggleModule', 'agenda');

        $this->assertSame(['agenda'], $user->fresh()->hidden_modules);
    }

    public function test_setting_the_default_page_from_inside_the_tutorial_persists(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Onboarding::class)->call('setDefaultPage', 'schedule');

        $this->assertSame('schedule', $user->fresh()->default_page);
    }

    public function test_settings_always_offers_the_tutorial_link_regardless_of_completion_state(): void
    {
        $neverSeen = User::factory()->create();
        $alreadyDone = User::factory()->create(['onboarding_completed_at' => now()]);

        Livewire::actingAs($neverSeen)->test(Settings::class)
            ->assertSee(route('onboarding'))
            ->assertSee('Tutorial starten');

        Livewire::actingAs($alreadyDone)->test(Settings::class)
            ->assertSee(route('onboarding'))
            ->assertSee('Nochmal ansehen');
    }
}
