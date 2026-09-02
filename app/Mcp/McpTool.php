<?php

namespace App\Mcp;

use App\Models\User;

/**
 * One MCP tool. Every concrete tool lives in App\Mcp\Tools, declares its own
 * name/description/inputSchema, and implements handle(). Availability
 * (ability + module gating) is data, not behaviour — McpServer reads
 * requiredAbility()/requiredModule() to decide what tools/list and
 * tools/call expose, so a tool class itself never has to check "am I allowed
 * to run" (see the "adaptive tool list" signature moment: this is what makes
 * it structural rather than a per-tool guard someone could forget to add).
 */
abstract class McpTool
{
    abstract public function name(): string;

    abstract public function description(): string;

    /** JSON Schema (object) describing tools/call's `arguments`. */
    abstract public function inputSchema(): array;

    /**
     * MCP tool annotations — untrusted hints for the client UI, never a
     * security boundary on their own (the real gate is requiredAbility()).
     *
     * @return array{readOnlyHint?: bool, destructiveHint?: bool, idempotentHint?: bool}
     */
    public function annotations(): array
    {
        return [];
    }

    /** Sanctum ability (App\Mcp\McpAbility::*) required to see/call this tool. Null = read is enough. */
    public function requiredAbility(): ?string
    {
        return McpAbility::READ;
    }

    /** App\Services\AppModules catalog key that must be visible for this tool to appear. Null = always. */
    public function requiredModule(): ?string
    {
        return null;
    }

    /**
     * Run the tool. Returns a plain, JSON-serialisable array — the transport
     * layer wraps it into the MCP `content`/`structuredContent` shape.
     * Throw App\Mcp\Exceptions\McpToolExecutionException for anything the
     * caller should see as a normal, structured tool error (bad id, bad
     * arguments, a delete confirmation that didn't match).
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    abstract public function handle(User $user, array $arguments): array;
}
