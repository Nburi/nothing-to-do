<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiDocsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/docs/api')->assertRedirect('/login');
    }

    public function test_an_authenticated_user_can_view_the_api_docs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/docs/api')
            ->assertOk()
            ->assertSee('API-Dokumentation')
            ->assertSee('/tasks', false);
    }
}
