<?php

namespace Tests\Feature;

use App\Livewire\Settings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApiTokensTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_create_a_named_api_token(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->set('newTokenName', 'iPhone Shortcuts')
            ->call('createApiToken')
            ->assertSet('newTokenName', '')
            ->assertSet('createdToken', fn ($token) => $token !== null);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'iPhone Shortcuts',
        ]);
    }

    public function test_a_blank_token_name_is_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->set('newTokenName', '   ')
            ->call('createApiToken')
            ->assertHasErrors(['newTokenName' => 'required']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_a_user_can_revoke_their_own_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('old device');

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->call('revokeApiToken', $token->accessToken->id);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_a_user_cannot_revoke_another_users_token(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $token = $owner->createToken('owner device');

        Livewire::actingAs($other)
            ->test(Settings::class)
            ->call('revokeApiToken', $token->accessToken->id);

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
    }
}
