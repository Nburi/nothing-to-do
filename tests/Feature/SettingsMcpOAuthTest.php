<?php

namespace Tests\Feature;

use App\Livewire\Settings;
use App\Mcp\McpAbility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The "Per OAuth verbinden" card: the permission toggles that stand in
 * for a personal access token's abilities, and the connected-client list.
 */
class SettingsMcpOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_account_may_write_but_not_delete_over_oauth(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->mcpOAuthCan(McpAbility::READ));
        $this->assertTrue($user->mcpOAuthCan(McpAbility::WRITE));
        $this->assertFalse($user->mcpOAuthCan(McpAbility::DELETE));
    }

    public function test_the_defaults_hold_on_a_model_that_was_never_reloaded(): void
    {
        // The fresh-model gotcha (CLAUDE.md §10): a DB-level default is absent
        // from a just-created model's attribute bag, and null is falsy — which
        // here would silently deny writing to every brand-new account.
        $user = new User;

        $this->assertTrue($user->mcpOAuthCan(McpAbility::WRITE));
        $this->assertFalse($user->mcpOAuthCan(McpAbility::DELETE));
    }

    public function test_the_toggles_save_immediately(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->call('toggleMcpOauthDelete')
            ->assertSet('mcpOauthDelete', true)
            ->call('toggleMcpOauthWrite')
            ->assertSet('mcpOauthWrite', false);

        $user->refresh();

        $this->assertTrue($user->mcp_oauth_delete);
        $this->assertFalse($user->mcp_oauth_write);
    }

    public function test_it_lists_one_row_per_connected_client_not_one_per_token(): void
    {
        $user = User::factory()->create();
        $client = $this->client('Claude');

        // A live connection mints a fresh access token every hour off its
        // refresh token; those must not read as separate connections.
        $this->token($user, $client);
        $this->token($user, $client);
        $this->token($user, $this->client('Some other agent'));

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->assertSee('Claude')
            ->assertSee('Some other agent')
            ->assertSeeInOrder(['Claude', 'Trennen']);

        $this->assertCount(2, Livewire::actingAs($user)->test(Settings::class)->instance()->mcpConnections);
    }

    public function test_it_never_shows_another_users_connection(): void
    {
        $user = User::factory()->create();
        $this->token(User::factory()->create(), $this->client('Fremder Client'));

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->assertDontSee('Fremder Client')
            ->assertSee('Noch nichts verbunden.');
    }

    public function test_disconnecting_revokes_the_refresh_token_too(): void
    {
        // Revoking only the access token would leave the client able to mint a
        // new one within the hour — the connection would not actually be over.
        $user = User::factory()->create();
        $client = $this->client('Claude');
        $token = $this->token($user, $client);
        $refresh = RefreshToken::forceCreate([
            'id' => 'refresh-'.$token->id,
            'access_token_id' => $token->id,
            'revoked' => false,
            'expires_at' => now()->addMonths(6),
        ]);

        Livewire::actingAs($user)
            ->test(Settings::class)
            ->call('revokeMcpConnection', $client->id)
            ->assertSee('Noch nichts verbunden.');

        $this->assertTrue($token->fresh()->revoked);
        $this->assertTrue($refresh->fresh()->revoked);
    }

    public function test_it_cannot_disconnect_another_users_connection(): void
    {
        $stranger = User::factory()->create();
        $client = $this->client('Claude');
        $token = $this->token($stranger, $client);

        Livewire::actingAs(User::factory()->create())
            ->test(Settings::class)
            ->call('revokeMcpConnection', $client->id);

        $this->assertFalse($token->fresh()->revoked);
    }

    /** Registered exactly the way POST /oauth/register registers Claude. */
    private function client(string $name): Client
    {
        return app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            name: $name,
            redirectUris: ['https://claude.ai/api/mcp/auth_callback'],
            confidential: false,
            enableDeviceFlow: false,
        );
    }

    private function token(User $user, Client $client): Token
    {
        return Token::forceCreate([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'client_id' => $client->id,
            'scopes' => ['mcp:use'],
            'revoked' => false,
            'expires_at' => now()->addHour(),
        ]);
    }
}
