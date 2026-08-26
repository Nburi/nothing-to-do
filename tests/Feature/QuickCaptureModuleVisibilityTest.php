<?php

namespace Tests\Feature;

use App\Livewire\QuickCapture;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class QuickCaptureModuleVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_target_is_offered_by_default(): void
    {
        $user = User::factory()->create();

        $targets = Livewire::actingAs($user)->test(QuickCapture::class)->get('availableTargets');

        $this->assertContains('craft', $targets);
        $this->assertContains('agenda', $targets);
    }

    public function test_hiding_a_module_removes_its_capture_target(): void
    {
        $user = User::factory()->create(['hidden_modules' => ['crafts']]);

        $targets = Livewire::actingAs($user)->test(QuickCapture::class)->get('availableTargets');

        $this->assertNotContains('craft', $targets);
        $this->assertContains('agenda', $targets);
    }

    public function test_setting_a_hidden_target_is_ignored(): void
    {
        $user = User::factory()->create(['hidden_modules' => ['crafts']]);

        $component = Livewire::actingAs($user)->test(QuickCapture::class)->call('setTarget', 'craft');

        $component->assertSet('target', 'inbox');
    }

    public function test_saving_against_a_hidden_target_fails_validation(): void
    {
        $user = User::factory()->create(['hidden_modules' => ['crafts']]);

        Livewire::actingAs($user)->test(QuickCapture::class)
            ->set('title', 'Etwas basteln')
            ->set('target', 'craft')
            ->call('save')
            ->assertHasErrors('target');

        $this->assertSame(0, $user->craftIdeas()->count());
    }
}
