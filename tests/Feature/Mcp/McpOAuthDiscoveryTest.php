<?php

namespace Tests\Feature\Mcp;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The discovery handshake Claude performs before it will even offer to
 * connect a custom connector. Every assertion here mirrors a specific,
 * documented requirement from Anthropic's "Authentication for connectors"
 * page — if one of these breaks, claude.ai fails with nothing more useful
 * than "Couldn't reach the MCP server", so they are worth pinning down.
 */
class McpOAuthDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_mcp_post_returns_401_pointing_at_the_resource_metadata(): void
    {
        // Claude only starts the OAuth flow when the 401 tells it where the
        // protected resource metadata lives. A 200, or a 401 without this
        // header, is a dead end.
        $response = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping']);

        $response->assertUnauthorized();

        $header = $response->headers->get('WWW-Authenticate');

        $this->assertNotNull($header, 'The 401 must carry a WWW-Authenticate header.');
        $this->assertStringContainsString('resource_metadata="', (string) $header);
        $this->assertStringContainsString('/.well-known/oauth-protected-resource/mcp', (string) $header);
    }

    public function test_protected_resource_metadata_matches_the_url_the_user_enters(): void
    {
        // "The protected resource metadata document's `resource` field must
        // match your MCP server URL exactly as the user enters it in Claude,
        // including any path component."
        $this->getJson('/.well-known/oauth-protected-resource/mcp')
            ->assertOk()
            ->assertJsonPath('resource', url('/mcp'))
            ->assertJsonPath('authorization_servers.0', url('/'));
    }

    public function test_authorization_server_metadata_advertises_what_claude_requires(): void
    {
        // Claude always sends an S256 PKCE challenge and expects the server to
        // say so; without a registration_endpoint it cannot register itself as
        // a client at all (no DCR = no connection, since a Pro/Max user has no
        // pre-registered client to paste in).
        $this->getJson('/.well-known/oauth-authorization-server')
            ->assertOk()
            ->assertJsonPath('issuer', url('/'))
            ->assertJsonPath('authorization_endpoint', route('passport.authorizations.authorize'))
            ->assertJsonPath('token_endpoint', route('passport.token'))
            ->assertJsonPath('registration_endpoint', url('/oauth/register'))
            ->assertJsonPath('code_challenge_methods_supported.0', 'S256')
            ->assertJsonPath('scopes_supported.0', 'mcp:use')
            ->assertJsonFragment(['grant_types_supported' => ['authorization_code', 'refresh_token']]);
    }

    public function test_dynamic_client_registration_accepts_claudes_callback(): void
    {
        $response = $this->postJson('/oauth/register', [
            'client_name' => 'Claude',
            'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('token_endpoint_auth_method', 'none')
            ->assertJsonPath('redirect_uris.0', 'https://claude.ai/api/mcp/auth_callback')
            ->assertJsonPath('scope', 'mcp:use');

        $this->assertDatabaseHas('oauth_clients', ['name' => 'Claude']);
    }

    public function test_dynamic_client_registration_accepts_a_claude_code_loopback_callback(): void
    {
        // Claude Code is a native client: RFC 8252 loopback on a port that
        // changes every session, so the allow-list has to be port-agnostic.
        $this->postJson('/oauth/register', [
            'client_name' => 'Claude Code',
            'redirect_uris' => ['http://localhost:3118/callback'],
        ])->assertCreated();
    }

    public function test_dynamic_client_registration_rejects_a_foreign_redirect_domain(): void
    {
        // config/mcp.php ships with '*' (any domain). Narrowing it is what
        // stops this endpoint from being a free OAuth-code redirector for
        // anyone who can POST to it.
        $this->postJson('/oauth/register', [
            'client_name' => 'Evil',
            'redirect_uris' => ['https://evil.example.com/callback'],
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_redirect_uri');
    }

    public function test_dynamic_client_registration_is_rate_limited(): void
    {
        // The package registers this route without a limit; routes/ai.php
        // re-registers it with one. If a package change ever moves the route,
        // there would be two of them and the limit could silently stop
        // applying — hence asserting both the count and the middleware.
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route): bool => $route->uri() === 'oauth/register');

        $this->assertCount(1, $routes, 'Exactly one /oauth/register route should be registered.');

        $middleware = app('router')->gatherRouteMiddleware($routes->first());

        // gatherRouteMiddleware() resolves the alias, so this is the class
        // name (Illuminate\Routing\Middleware\ThrottleRequests:10,1), not
        // the 'throttle:10,1' string written in routes/ai.php.
        $this->assertTrue(
            collect($middleware)->contains(fn ($m): bool => is_string($m) && str_contains($m, 'ThrottleRequests')),
            'Dynamic client registration must stay rate limited.',
        );
    }

    public function test_the_device_grant_and_passports_client_management_api_are_not_exposed(): void
    {
        foreach (['oauth/device', 'oauth/device/code', 'oauth/clients', 'oauth/scopes', 'oauth/personal-access-tokens'] as $uri) {
            $this->assertFalse(
                collect(app('router')->getRoutes()->getRoutes())->contains(fn ($route): bool => $route->uri() === $uri),
                "Route [{$uri}] should not be registered.",
            );
        }
    }
}
