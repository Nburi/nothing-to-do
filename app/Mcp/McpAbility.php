<?php

namespace App\Mcp;

/**
 * The three Sanctum token abilities the MCP server checks (see
 * Settings::createApiToken() — a new token's abilities are chosen there,
 * `mcp:read` implied, `mcp:write`/`mcp:delete` opt-in checkboxes). A legacy
 * token created before this feature (or the Shortcuts flow) carries `['*']`,
 * which Sanctum's own PersonalAccessToken::can() already treats as
 * satisfying every ability check — no migration needed for existing tokens.
 */
final class McpAbility
{
    /** Every read-only tool. Implied by having a valid token at all — no MCP token is ever read-less. */
    public const READ = 'mcp:read';

    /** Tools that create/update/reorder/complete/change settings — reversible mutations. */
    public const WRITE = 'mcp:write';

    /** Tools that permanently remove data. Off by default at token-creation time. */
    public const DELETE = 'mcp:delete';
}
