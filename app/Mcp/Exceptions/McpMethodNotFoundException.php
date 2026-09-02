<?php

namespace App\Mcp\Exceptions;

use RuntimeException;

/** An unsupported top-level JSON-RPC method (anything but initialize/tools/list/tools/call/ping). */
class McpMethodNotFoundException extends RuntimeException
{
    public function __construct(private readonly string $method)
    {
        parent::__construct("Method not found: {$method}");
    }

    public function method(): string
    {
        return $this->method;
    }
}
