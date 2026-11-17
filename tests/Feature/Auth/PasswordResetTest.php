<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_the_unknown_email_error_is_shown_in_german_not_english(): void
    {
        // Regression: this project had no lang/ directory at all, so this
        // exact failure rendered Laravel's raw English default —
        // "We can't find a user with that email address." — on an
        // otherwise fully German page. See lang/de/passwords.php.
        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => 'nobody-uses-this-address@example.com',
        ]);

        $response->assertSessionHasErrors(['email' => 'Wir konnten keinen Nutzer mit dieser E-Mail-Adresse finden.']);

        $message = $response->getSession()->get('errors')->getBag('default')->first('email');
        $this->assertStringNotContainsString("can't find a user", $message);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }
}
