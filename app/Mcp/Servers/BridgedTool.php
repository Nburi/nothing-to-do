<?php

namespace App\Mcp\Servers;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpServer;
use App\Mcp\McpTool;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * Adapts one of this app's own App\Mcp\Tools\* tools onto Laravel MCP's Tool
 * primitive, so the OAuth server (App\Mcp\Servers\NothingToDoServer) and the
 * Sanctum server (App\Http\Controllers\Api\McpController) expose the exact
 * same 19 tools from the exact same code.
 *
 * Deliberately a bridge rather than 19 rewritten Laravel\Mcp\Server\Tool
 * subclasses: the tool layer is the tested, documented part of this feature
 * (McpToolsTest calls it directly, /docs/mcp is generated from it), and it
 * already owns name/description/inputSchema/annotations plus the
 * ability+module gating that makes the adaptive tool list structural. A
 * rewrite would have duplicated all of that into a second implementation
 * that could drift — the same "don't build a second mutation path for the
 * same thing" rule TaskMutator was extracted for.
 *
 * Laravel MCP owns everything above the tool: the JSON-RPC framing, the
 * Streamable HTTP transport, protocol-version negotiation, pagination, and
 * the OAuth discovery/registration routes.
 */
class BridgedTool extends Tool
{
    public function __construct(
        public readonly McpTool $tool,
        protected readonly McpServer $registry,
    ) {}

    public function name(): string
    {
        return $this->tool->name();
    }

    public function title(): string
    {
        return Str::headline($this->tool->name());
    }

    public function description(): string
    {
        return $this->tool->description();
    }

    /**
     * Laravel MCP normally builds inputSchema from schema(JsonSchema) through
     * its fluent builder. Our tools already carry a literal JSON Schema array,
     * so toArray() is overridden below to emit that verbatim and this stays
     * empty — returning anything here would silently win over the real schema.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $schema = $this->tool->inputSchema();
        $schema['properties'] ??= (object) [];

        $annotations = $this->tool->annotations();

        return [
            'name' => $this->name(),
            'title' => $this->title(),
            'description' => $this->description(),
            'inputSchema' => $schema,
            'annotations' => $annotations === [] ? (object) [] : $annotations,
        ];
    }

    /**
     * The adaptive tool list, unchanged from the Sanctum server: a tool the
     * user's module settings or this connection's permissions don't allow is
     * absent from tools/list entirely — and because Laravel MCP resolves
     * tools/call from the same filtered collection, calling it anyway fails
     * with the identical "Tool [x] not found." a genuinely nonexistent name
     * gets. A connection never learns what it would need to unlock something.
     */
    public function shouldRegister(Request $request): bool
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return false;
        }

        return $this->registry->isAvailableTo(
            $this->tool,
            $user,
            fn (string $ability): bool => $user->mcpOAuthCan($ability),
        );
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $data = $this->tool->handle($user, $request->all());
        } catch (McpToolExecutionException|ValidationException $e) {
            return Response::error($e instanceof ValidationException
                ? collect($e->errors())->flatten()->implode(' ')
                : $e->getMessage());
        }

        // Response::structured() refuses an empty array, and a tool is allowed
        // to legitimately have nothing to report back.
        return $data === []
            ? Response::text('{}')
            : Response::structured($data);
    }
}
