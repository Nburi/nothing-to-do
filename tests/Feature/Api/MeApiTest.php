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
            ->assertJsonPath('counts.today', 1);
    }

    public function test_it_updates_timezone_settings(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->patchJson('/api/me', [
            'timezone_offset' => 1,
            'timezone_auto_dst' => true,
        ])->assertOk();

        $user->refresh();
        $this->assertTrue((bool) $user->timezone_auto_dst);
    }
}
