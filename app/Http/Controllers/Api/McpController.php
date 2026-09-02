<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mcp\Exceptions\McpMethodNotFoundException;
use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\Exceptions\McpUnknownToolException;
use App\Mcp\McpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The MCP "Streamable HTTP" transport (see modelcontextprotocol.io,
 * spec 2025-06-18): a single JSON-RPC 2.0 endpoint. This server is
 * deliberately stateless — every request re-authenticates via its own
 * Sanctum bearer token and there is no server-side session to track, so it
 * always answers with a single `application/json` response rather than
 * upgrading to an SSE stream (both are valid per spec; a stateless server
 * that never needs to push multiple messages for one request has no reason
 * to open one). No `Mcp-Session-Id` is issued for the same reason.
 *
 * Supported methods: initialize, notifications/initialized (ack no-op),
 * ping, tools/list, tools/call. Nothing else in the spec (resources,
 * prompts, sampling, roots) is implemented — this server only ever
 * declares the `tools` capability.
 */
class McpController extends Controller
{
    private const PROTOCOL_VERSION = '2025-06-18';

    public function __construct(private readonly McpServer $server) {}

    public function __invoke(Request $request): Response
    {
        $payload = $request->json()->all();

        if (! is_array($payload) || ($payload['jsonrpc'] ?? null) !== '2.0' || ! isset($payload['method'])) {
            return $this->errorResponse($payload['id'] ?? null, -32600, 'Invalid Request');
        }

        $id = $payload['id'] ?? null;
        $method = $payload['method'];
        $params = is_array($payload['params'] ?? null) ? $payload['params'] : [];
        $isNotification = ! array_key_exists('id', $payload);

        try {
            $result = match ($method) {
                'initialize' => $this->initialize($params),
                'notifications/initialized' => [],
                'ping' => (object) [],
                'tools/list' => ['tools' => $this->server->listToolDefinitions($request->user(), $this->tokenCan($request))],
                'tools/call' => $this->callTool($request, $params),
                default => throw new McpMethodNotFoundException((string) $method),
            };
        } catch (McpMethodNotFoundException $e) {
            return $this->errorResponse($id, -32601, $e->getMessage());
        } catch (McpUnknownToolException $e) {
            return $this->errorResponse($id, -32602, $e->getMessage());
        } catch (ValidationException $e) {
            return $this->errorResponse($id, -32602, collect($e->errors())->flatten()->implode(' '));
        } catch (Throwable $e) {
            report($e);

            return $this->errorResponse($id, -32603, 'Internal error');
        }

        if ($isNotification) {
            return response()->noContent(Response::HTTP_ACCEPTED);
        }

        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
    }

    /** @param  array<string, mixed>  $params */
    private function initialize(array $params): array
    {
        return [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => ['name' => 'nothing-to-do', 'version' => '1.0.0'],
            'instructions' => 'Read and organize this user\'s tasks, projects, task groups, agenda, '
                .'categories, progress and settings in nothing-to-do. Which tools you see adapts live to '
                .'what this user has enabled in Settings and to what this token is allowed to do — an '
                .'absent tool means "not available right now", not a bug.',
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{content: list<array{type: string, text: string}>, structuredContent?: array, isError: bool}
     */
    private function callTool(Request $request, array $params): array
    {
        $name = $params['name'] ?? null;
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        if (! is_string($name) || $name === '') {
            throw new McpUnknownToolException((string) ($name ?? ''));
        }

        try {
            $data = $this->server->call($request->user(), $this->tokenCan($request), $name, $arguments);

            return [
                'content' => [['type' => 'text', 'text' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]],
                'structuredContent' => $data,
                'isError' => false,
            ];
        } catch (McpToolExecutionException|ValidationException $e) {
            $message = $e instanceof ValidationException
                ? collect($e->errors())->flatten()->implode(' ')
                : $e->getMessage();

            return [
                'content' => [['type' => 'text', 'text' => $message]],
                'isError' => true,
            ];
        }
    }

    /**
     * A callable, not a raw abilities array — delegates to Sanctum's own
     * HasApiTokens::tokenCan(), which already correctly handles a real
     * PersonalAccessToken (abilities array, '*' wildcard) and a
     * session-authenticated TransientToken (always true) alike, rather than
     * this class trying to re-derive that from the token object itself.
     *
     * @return callable(string): bool
     */
    private function tokenCan(Request $request): callable
    {
        $user = $request->user();

        return fn (string $ability): bool => $user !== null && $user->tokenCan($ability);
    }

    private function errorResponse(mixed $id, int $code, string $message): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => ['code' => $code, 'message' => $message],
        ]);
    }
}
