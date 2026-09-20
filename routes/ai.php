<?php

use App\Mcp\Servers\NothingToDoServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;

/*
|--------------------------------------------------------------------------
| MCP Routes
|--------------------------------------------------------------------------
|
| The OAuth-authenticated MCP server. This exists because claude.ai and
| Claude Desktop cannot connect to the Sanctum-protected /api/mcp endpoint
| at all: an individual user's "Add custom connector" dialog offers a server
| URL plus an optional OAuth client ID/secret and nothing else — there is no
| field for a personal access token. (Anthropic's `static_headers` auth type
| does allow a fixed bearer token, but it is beta and set up by an
| organization administrator, not by a Pro/Max user adding a connector.)
|
| Mcp::oauthRoutes() registers everything Claude discovers on its own:
|   GET  /.well-known/oauth-protected-resource[/{path}]  (RFC 9728)
|   GET  /.well-known/oauth-authorization-server[/{path}] (RFC 8414)
|   POST /oauth/register                                  (RFC 7591, DCR)
| Passport itself supplies /oauth/authorize and /oauth/token.
|
| The `auth:api` middleware is the Passport guard (config/auth.php). An
| unauthenticated POST returns 401 plus the WWW-Authenticate header pointing
| at the protected-resource document — the handshake that actually starts
| Claude's OAuth flow — via Laravel MCP's own AddWwwAuthenticateHeader
| middleware, which Mcp::web() attaches for us.
|
*/

Mcp::oauthRoutes();

/*
 * Mcp::oauthRoutes() registers POST /oauth/register with no rate limit. It is
 * unauthenticated by necessity (dynamic client registration happens before any
 * token exists) and writes a row to oauth_clients on every call, so left alone
 * it is an open invitation to fill that table. Re-registering the same
 * method+URI replaces the package's route in the collection — the only seam it
 * offers — and McpOAuthDiscoveryTest asserts the throttle really is attached,
 * so a future package change that moves this route cannot silently drop it.
 *
 * Ten a minute is far more than real use: Claude registers once per connection.
 */
Route::post('/oauth/register', OAuthRegisterController::class)
    ->middleware('throttle:10,1');

Mcp::web('/mcp', NothingToDoServer::class)
    ->middleware(['auth:api', 'throttle:mcp']);
