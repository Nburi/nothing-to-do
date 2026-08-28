<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_login_stamps_last_login_at(): void
    {
        $user = User::factory()->create(['last_login_at' => null]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_first_ever_login_does_not_flash_a_welcome_back_message(): void
    {
        $user = User::factory()->create(['last_login_at' => null]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertNull(session('welcome_back_message'));
    }

    public function test_a_login_shortly_after_the_last_one_does_not_flash_a_welcome_back_message(): void
    {
        $user = User::factory()->create(['last_login_at' => now()->subDays(3)]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertNull(session('welcome_back_message'));
    }

    public function test_a_login_after_a_long_gap_flashes_a_welcome_back_message(): void
    {
        $user = User::factory()->create([
            'last_login_at' => now()->subDays(User::WELCOME_BACK_AWAY_DAYS + 1),
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $message = session('welcome_back_message');
        $this->assertNotNull($message);
        $this->assertContains($message, User::WELCOME_BACK_MESSAGES);
    }

    public function test_a_login_at_exactly_the_threshold_flashes_a_welcome_back_message(): void
    {
        $user = User::factory()->create([
            'last_login_at' => now()->subDays(User::WELCOME_BACK_AWAY_DAYS),
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertNotNull(session('welcome_back_message'));
    }
}
