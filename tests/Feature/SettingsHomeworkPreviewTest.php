<?php

namespace Tests\Feature;

use App\Livewire\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsHomeworkPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_homework_preview_is_on_by_default(): void
    {
        $this->assertTrue(User::factory()->create()->fresh()->homework_preview_enabled);
    }

    public function test_the_setting_can_be_turned_off_and_back_on(): void
    {
        $user = User::factory()->create(['homework_preview_enabled' => true]);

        Livewire::actingAs($user)->test(Settings::class)->call('toggleHomeworkPreviewEnabled');
        $this->assertFalse($user->fresh()->homework_preview_enabled);

        Livewire::actingAs($user)->test(Settings::class)->call('toggleHomeworkPreviewEnabled');
        $this->assertTrue($user->fresh()->homework_preview_enabled);
    }
}
