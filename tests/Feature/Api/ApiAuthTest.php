<?php

namespace Tests\Feature\Api;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/tasks')->assertUnauthorized();
        $this->getJson('/api/me')->assertUnauthorized();
        $this->getJson('/api/projects')->assertUnauthorized();
        $this->getJson('/api/schedule-events')->assertUnauthorized();
    }

    public function test_a_valid_token_authenticates_the_request(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_a_task_belonging_to_another_user_is_not_reachable(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $task = Task::factory()->for($owner)->create();

        Sanctum::actingAs($other);

        $this->getJson("/api/tasks/{$task->id}")->assertNotFound();
    }
}
