<?php

namespace App\Mcp\Exceptions;

use RuntimeException;

/**
 * A tool ran but couldn't complete — bad input, a task that doesn't belong
 * to this user, a delete confirmation that didn't match. Mapped to a
 * `tools/call` result with `isError: true` (a normal, structured response
 * the calling model can read and react to), never a JSON-RPC protocol error
 * — per the MCP spec, protocol errors are for "unknown method"/"unknown
 * tool"/transport problems, not business-logic failures.
 */
class McpToolExecutionException extends RuntimeException
{
    //
}
