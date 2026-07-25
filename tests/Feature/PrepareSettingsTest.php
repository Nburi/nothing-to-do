<?php

namespace Tests\Feature;

use App\Livewire\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PrepareSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_show_the_vorbereitung_card(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Settings::class)
            ->assertOk()
            ->assertSee('Vorbereitung')
            ->assertSee('Erinnerung');
    }

    public function test_it_loads_saved_prepare_settings_on_mount(): void
    {
        $user = User::factory()->create([
            'prepare_time_of_day' => 'morning',
            'prepare_reminder_mode' => 'fixed',
            'prepare_reminder_time' => '20:30',
        ]);
        $this->actingAs($user);

        Livewire::test(Settings::class)
            ->assertSet('prepareTimeOfDay', 'morning')
            ->assertSet('prepareReminderMode', 'fixed')
            ->assertSet('prepareReminderTime', '20:30');
    }

    public function test_it_sets_the_time_of_day_immediately(): void
    {
        $user = User::factory()->create(['prepare_time_of_day' => 'evening']);
        $this->actingAs($user);

        Livewire::test(Settings::class)->call('setPrepareTimeOfDay', 'morning');

        $this->assertSame('morning', $user->refresh()->prepare_time_of_day);
    }

    public function test_it_rejects_an_invalid_time_of_day(): void
    {
        $user = User::factory()->create(['prepare_time_of_day' => 'evening']);
        $this->actingAs($user);

        Livewire::test(Settings::class)->call('setPrepareTimeOfDay', 'noon');

        $this->assertSame('evening', $user->refresh()->prepare_time_of_day);
    }

    public function test_it_sets_the_reminder_mode_immediately(): void
    {
        $user = User::factory()->create(['prepare_reminder_mode' => 'off']);
        $this->actingAs($user);

        Livewire::test(Settings::class)->call('setPrepareReminderMode', 'automatic');

        $this->assertSame('automatic', $user->refresh()->prepare_reminder_mode);
    }

    public function test_it_rejects_an_invalid_reminder_mode(): void
    {
        $user = User::factory()->create(['prepare_reminder_mode' => 'off']);
        $this->actingAs($user);

        Livewire::test(Settings::class)->call('setPrepareReminderMode', 'sometimes');

        $this->assertSame('off', $user->refresh()->prepare_reminder_mode);
    }

    public function test_it_saves_a_fixed_reminder_time(): void
    {
        $user = User::factory()->create(['prepare_reminder_mode' => 'fixed']);
        $this->actingAs($user);

        Livewire::test(Settings::class)
            ->set('prepareReminderTime', '19:45')
            ->call('savePrepareReminderTime')
            ->assertHasNoErrors();

        $this->assertSame('19:45', $user->refresh()->prepare_reminder_time);
    }

    public function test_it_rejects_a_malformed_reminder_time(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Settings::class)
            ->set('prepareReminderTime', 'not-a-time')
            ->call('savePrepareReminderTime')
            ->assertHasErrors(['prepareReminderTime']);
    }
}
