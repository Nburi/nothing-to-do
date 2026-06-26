<?php

namespace Tests\Feature;

use App\Livewire\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduleSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_show_brief_and_pomodoro(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Settings::class)
            ->assertOk()
            ->assertSee('Brief')
            ->assertSee('Pomodoro');
    }

    public function test_it_saves_brief_and_pomodoro_settings(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Settings::class)
            ->set('briefWhen', 'morning')
            ->set('briefTime', '07:30')
            ->set('pWork', 50)
            ->set('pShortBreak', 10)
            ->set('pLongBreak', 20)
            ->set('pLongEvery', 3)
            ->call('saveSchedule')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'brief_when' => 'morning',
            'brief_time' => '07:30',
            'pomodoro_work' => 50,
            'pomodoro_short_break' => 10,
            'pomodoro_long_break' => 20,
            'pomodoro_long_every' => 3,
        ]);
    }

    public function test_it_validates_ranges(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Settings::class)
            ->set('briefWhen', 'whenever')
            ->set('pWork', 0)
            ->set('pLongEvery', 99)
            ->call('saveSchedule')
            ->assertHasErrors(['briefWhen', 'pWork', 'pLongEvery']);
    }

    public function test_start_brief_redirects_to_the_brief(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Settings::class)
            ->call('startBrief')
            ->assertRedirect(route('brief'));
    }
}
