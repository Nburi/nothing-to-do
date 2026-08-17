<?php

namespace Tests\Feature;

use App\Livewire\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProgressSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_show_the_fortschritt_card(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Settings::class)
            ->assertOk()
            ->assertSee('Fortschritt')
            ->assertSee('Tagesziel')
            ->assertSee('Serie in Gefahr');
    }

    public function test_it_loads_saved_progress_settings_on_mount(): void
    {
        $user = User::factory()->create([
            'daily_task_goal' => 8,
            'notify_daily_reminder' => true,
            'daily_reminder_time' => '20:15',
            'notify_streak_risk' => true,
        ]);
        $this->actingAs($user);

        Livewire::test(Settings::class)
            ->assertSet('dailyTaskGoal', 8)
            ->assertSet('notifyDailyReminder', true)
            ->assertSet('dailyReminderTime', '20:15')
            ->assertSet('notifyStreakRisk', true);
    }

    public function test_it_saves_a_valid_daily_goal(): void
    {
        $user = User::factory()->create(['daily_task_goal' => 5]);
        $this->actingAs($user);

        Livewire::test(Settings::class)
            ->set('dailyTaskGoal', 12)
            ->call('saveDailyGoal')
            ->assertHasNoErrors();

        $this->assertSame(12, $user->refresh()->daily_task_goal);
    }

    public function test_it_rejects_a_daily_goal_outside_the_allowed_range(): void
    {
        $user = User::factory()->create(['daily_task_goal' => 5]);
        $this->actingAs($user);

        Livewire::test(Settings::class)
            ->set('dailyTaskGoal', 0)
            ->call('saveDailyGoal')
            ->assertHasErrors(['dailyTaskGoal']);

        Livewire::test(Settings::class)
            ->set('dailyTaskGoal', 31)
            ->call('saveDailyGoal')
            ->assertHasErrors(['dailyTaskGoal']);

        $this->assertSame(5, $user->refresh()->daily_task_goal);
    }

    public function test_it_toggles_the_daily_reminder_immediately(): void
    {
        $user = User::factory()->create(['notify_daily_reminder' => false]);
        $this->actingAs($user);

        Livewire::test(Settings::class)->call('toggleNotifyDailyReminder');

        $this->assertTrue((bool) $user->refresh()->notify_daily_reminder);
    }

    public function test_it_saves_a_valid_daily_reminder_time(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Settings::class)
            ->set('dailyReminderTime', '18:30')
            ->call('saveDailyReminderTime')
            ->assertHasNoErrors();

        $this->assertSame('18:30', $user->refresh()->daily_reminder_time);
    }

    public function test_it_rejects_a_malformed_daily_reminder_time(): void
    {
        $user = User::factory()->create(['daily_reminder_time' => '19:00']);
        $this->actingAs($user);

        Livewire::test(Settings::class)
            ->set('dailyReminderTime', 'nope')
            ->call('saveDailyReminderTime')
            ->assertHasErrors(['dailyReminderTime']);

        $this->assertSame('19:00', $user->refresh()->daily_reminder_time);
    }

    public function test_it_toggles_streak_risk_reminder_immediately(): void
    {
        $user = User::factory()->create(['notify_streak_risk' => false]);
        $this->actingAs($user);

        Livewire::test(Settings::class)->call('toggleNotifyStreakRisk');

        $this->assertTrue((bool) $user->refresh()->notify_streak_risk);
    }
}
