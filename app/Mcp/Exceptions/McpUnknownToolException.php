<?php

namespace App\Mcp\Exceptions;

use RuntimeException;

/**
 * Thrown for a tool name that either doesn't exist at all, or exists but
 * isn't available to this user/token right now (module hidden, ability
 * missing). Deliberately the SAME exception, and the SAME resulting
 * JSON-RPC error, for both cases — see McpServer::call(): a token without
 * `mcp:delete` gets "Unknown tool: delete_task", not "Forbidden: delete_task",
 * so a caller learns nothing about what capability it would need to unlock.
 * This is the mechanism behind the signature moment: tools/list already
 * omits what isn't available, and tools/call is equally blind to it.
 */
class McpUnknownToolException extends RuntimeException
{
    public function __construct(private readonly string $toolName)
    {
        parent::__construct("Unknown tool: {$toolName}");
    }

    public function toolName(): string
    {
        return $this->toolName;
    }
}
