<?php

namespace Tests\Feature;

use App\Livewire\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggling_a_module_hides_it_and_persists(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Settings::class)->call('toggleModule', 'agenda');

        $this->assertSame(['agenda'], $user->fresh()->hidden_modules);
    }

    public function test_toggling_a_hidden_module_reveals_it_again(): void
    {
        $user = User::factory()->create(['hidden_modules' => ['agenda']]);

        Livewire::actingAs($user)->test(Settings::class)->call('toggleModule', 'agenda');

        $this->assertSame([], $user->fresh()->hidden_modules);
    }

    public function test_toggling_one_module_never_disturbs_another(): void
    {
        $user = User::factory()->create(['hidden_modules' => ['crafts']]);

        Livewire::actingAs($user)->test(Settings::class)->call('toggleModule', 'agenda');

        $hidden = $user->fresh()->hidden_modules;
        $this->assertContains('crafts', $hidden);
        $this->assertContains('agenda', $hidden);
    }

    public function test_toggling_an_unknown_key_is_a_no_op(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Settings::class)->call('toggleModule', 'not-a-real-module');

        $this->assertNull($user->fresh()->hidden_modules);
    }

    public function test_hiding_the_current_default_page_resets_it_to_the_board(): void
    {
        $user = User::factory()->create(['default_page' => 'agenda']);

        $component = Livewire::actingAs($user)->test(Settings::class)->call('toggleModule', 'agenda');

        $component->assertSet('defaultPage', 'app');
        $this->assertSame('app', $user->fresh()->default_page);
    }

    public function test_hiding_a_module_that_is_not_the_default_page_leaves_it_untouched(): void
    {
        $user = User::factory()->create(['default_page' => 'schedule']);

        Livewire::actingAs($user)->test(Settings::class)->call('toggleModule', 'agenda');

        $this->assertSame('schedule', $user->fresh()->default_page);
    }

    public function test_set_default_page_accepts_a_visible_module(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(Settings::class)->call('setDefaultPage', 'agenda');

        $component->assertSet('defaultPage', 'agenda');
        $this->assertSame('agenda', $user->fresh()->default_page);
    }

    public function test_set_default_page_accepts_the_board(): void
    {
        $user = User::factory()->create(['default_page' => 'agenda']);

        Livewire::actingAs($user)->test(Settings::class)->call('setDefaultPage', 'app');

        $this->assertSame('app', $user->fresh()->default_page);
    }

    public function test_set_default_page_rejects_a_hidden_module(): void
    {
        $user = User::factory()->create(['hidden_modules' => ['agenda'], 'default_page' => 'app']);

        $component = Livewire::actingAs($user)->test(Settings::class)->call('setDefaultPage', 'agenda');

        $component->assertSet('defaultPage', 'app');
        $this->assertSame('app', $user->fresh()->default_page);
    }

    public function test_set_default_page_rejects_an_unknown_key(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(Settings::class)->call('setDefaultPage', 'not-a-real-module');

        $this->assertSame('app', $user->fresh()->default_page);
    }

    public function test_the_module_card_renders_in_the_general_tab(): void
    {
        $user = User::factory()->create();

        $html = Livewire::actingAs($user)->test(Settings::class)->html();

        $this->assertStringContainsString('Module', $html);
        $this->assertStringContainsString('Startseite', $html);
    }
}
