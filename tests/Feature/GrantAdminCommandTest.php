<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrantAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_grants_admin_by_email(): void
    {
        $user = User::factory()->create(['email' => 'niels@example.com', 'is_admin' => false]);

        $this->artisan('admin:grant', ['email' => 'niels@example.com'])->assertSuccessful();

        $this->assertTrue($user->fresh()->is_admin);
    }

    public function test_it_revokes_admin_with_the_revoke_flag(): void
    {
        $user = User::factory()->create(['email' => 'niels@example.com', 'is_admin' => true]);

        $this->artisan('admin:grant', ['email' => 'niels@example.com', '--revoke' => true])->assertSuccessful();

        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_it_fails_for_an_unknown_email(): void
    {
        $this->artisan('admin:grant', ['email' => 'nobody@example.com'])->assertFailed();
    }
}
