<?php

namespace Tests\Feature\Api;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_account_info_and_counts(): void
    {
        $user = User::factory()->create(['name' => 'Nico']);
        Task::factory()->for($user)->inbox()->create();
        Task::factory()->for($user)->todos()->today()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/me');

        $response->assertOk()
            ->assertJsonPath('name', 'Nico')
            ->assertJsonPath('counts.inbox', 1)
            ->assertJsonPath('counts.today', 1)
            ->assertJsonPath('pomodoro_autostart', false)
            ->assertJsonPath('notify_event_start', false)
            ->assertJsonPath('notify_pomo_start', false)
            ->assertJsonPath('notify_break_start', false)
            ->assertJsonStructure(['pomodoro' => ['work', 'short_break', 'long_break', 'long_every']]);
    }

    public function test_it_updates_pomodoro_and_timezone_settings(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/me', [
            'pomodoro_work' => 50,
            'timezone_offset' => 1,
            'timezone_auto_dst' => true,
        ])->assertOk()->assertJsonPath('pomodoro.work', 50);

        $user->refresh();
        $this->assertSame(50, $user->pomodoro_work);
        $this->assertTrue((bool) $user->timezone_auto_dst);
    }

    public function test_it_updates_autostart_and_notification_settings(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/me', [
            'pomodoro_autostart' => true,
            'notify_event_start' => true,
            'notify_pomo_start' => true,
            'notify_break_start' => true,
        ])
            ->assertOk()
            ->assertJsonPath('pomodoro_autostart', true)
            ->assertJsonPath('notify_event_start', true)
            ->assertJsonPath('notify_pomo_start', true)
            ->assertJsonPath('notify_break_start', true);

        $user->refresh();
        $this->assertTrue((bool) $user->pomodoro_autostart);
        $this->assertTrue((bool) $user->notify_event_start);
        $this->assertTrue((bool) $user->notify_pomo_start);
        $this->assertTrue((bool) $user->notify_break_start);
    }
}
