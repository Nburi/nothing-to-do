<?php

namespace App\Mcp\Support;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Runs one of the REST API controllers on behalf of an MCP tool call, in-process.
 *
 * The schedule domain (events with recurrence tombstones, Pomodoro phase math,
 * category task links, …) already has its rules in the REST controllers. A second
 * implementation inside every MCP tool is exactly the drift CLAUDE.md §10 warns
 * about ("don't build a second mutation path for the same thing"), so a tool
 * builds a synthetic request for the user and lets the controller do the work.
 * Validation errors propagate as ValidationException (both MCP transports already
 * turn that into a normal tool error); 4xx/5xx JSON responses and a missing model
 * become McpToolExecutionException.
 */
final class ControllerBridge
{
    /**
     * @param  class-string  $controller
     * @param  array<string, mixed>  $input  query (GET) or body (anything else)
     * @param  array<string, mixed>  $routeParams  e.g. ['id' => 12]
     * @return mixed the decoded response body, with a resource's {"data": …} envelope removed
     */
    public static function call(User $user, string $controller, string $action, array $input = [], string $method = 'GET', array $routeParams = []): mixed
    {
        $request = Request::create('/api/mcp-bridge', $method, $input);
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => $user);

        try {
            $response = app()->call([app($controller), $action], ['request' => $request] + $routeParams);
        } catch (ModelNotFoundException) {
            throw new McpToolExecutionException('Not found for this account.');
        }

        if (! $response instanceof JsonResponse) {
            return null;
        }

        $body = json_decode($response->getContent() ?: 'null', true);

        if ($response->getStatusCode() >= 400) {
            throw new McpToolExecutionException(is_array($body) ? (string) ($body['message'] ?? 'Request failed.') : 'Request failed.');
        }

        return is_array($body) && array_key_exists('data', $body) && count($body) === 1 ? $body['data'] : $body;
    }
}
