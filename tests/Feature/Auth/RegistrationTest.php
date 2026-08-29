<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        // A brand-new account has never seen the onboarding tutorial, so
        // registration (unlike login) sends it there first instead of the
        // board — see RegisteredUserController.
        $response->assertRedirect(route('onboarding', absolute: false));
    }

    /** The hidden timezone_offset/timezone_auto_dst fields (see resources/js/app.js's detectTimezoneDefaults()) become the new account's default — see RegisteredUserController. */
    public function test_registration_stores_a_browser_detected_timezone(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'timezone_offset' => '1',
            'timezone_auto_dst' => '1',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'timezone_offset' => 1,
            'timezone_auto_dst' => true,
        ]);
    }

    /** Registration must not depend on JS having run — a missing/garbage value is silently ignored, leaving the column at its DB default. */
    public function test_registration_ignores_a_missing_or_invalid_timezone_offset(): void
    {
        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'timezone_offset' => 'not-a-number',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'timezone_offset' => 0,
            'timezone_auto_dst' => false,
        ]);
    }
}
