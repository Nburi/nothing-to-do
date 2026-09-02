<?php

namespace App\Mcp;

use App\Mcp\Exceptions\McpUnknownToolException;
use App\Mcp\Tools\CompleteTaskTool;
use App\Mcp\Tools\CreateAgendaEntryTool;
use App\Mcp\Tools\CreateTaskTool;
use App\Mcp\Tools\DeleteTaskTool;
use App\Mcp\Tools\GetBoardTool;
use App\Mcp\Tools\GetProgressTool;
use App\Mcp\Tools\GetSettingsTool;
use App\Mcp\Tools\GetTaskTool;
use App\Mcp\Tools\ListAgendaEntriesTool;
use App\Mcp\Tools\ListCategoriesTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListTaskGroupsTool;
use App\Mcp\Tools\ListTasksTool;
use App\Mcp\Tools\ReopenTaskTool;
use App\Mcp\Tools\SetDefaultLandingPageTool;
use App\Mcp\Tools\SetListConceptTool;
use App\Mcp\Tools\SetModuleVisibilityTool;
use App\Mcp\Tools\SetTaskOrderTool;
use App\Mcp\Tools\UpdateTaskTool;
use App\Models\User;
use App\Services\AppModules;

/**
 * The MCP tool registry: what tools exist, and — the load-bearing part —
 * which of them are actually visible to a given user/token right now. Both
 * tools/list and tools/call go through availableTools(), so the two can
 * never disagree about what's callable (the signature moment: what
 * tools/list doesn't show, tools/call also refuses, with the identical
 * "unknown tool" error either way — see McpUnknownToolException).
 */
class McpServer
{
    /** @var list<McpTool> */
    protected array $tools;

    public function __construct()
    {
        $this->tools = [
            // Read
            new ListTasksTool,
            new GetBoardTool,
            new GetTaskTool,
            new ListProjectsTool,
            new ListTaskGroupsTool,
            new ListCategoriesTool,
            new ListAgendaEntriesTool,
            new GetProgressTool,
            new GetSettingsTool,
            // Write
            new CreateTaskTool,
            new UpdateTaskTool,
            new CompleteTaskTool,
            new ReopenTaskTool,
            new SetTaskOrderTool,
            new SetListConceptTool,
            new SetModuleVisibilityTool,
            new SetDefaultLandingPageTool,
            new CreateAgendaEntryTool,
            // Delete
            new DeleteTaskTool,
        ];
    }

    /**
     * @param  callable(string): bool  $tokenCan  typically $user->tokenCan(...) — see
     *                                            McpController::tokenCan(). Taking a callable (rather than a raw abilities
     *                                            array) means this class never has to know how to read a Sanctum token's
     *                                            abilities itself — Laravel\Sanctum\HasApiTokens::tokenCan() already
     *                                            handles both a real PersonalAccessToken (abilities array, '*' wildcard)
     *                                            and a session-authenticated TransientToken (always true) correctly.
     * @return list<McpTool>
     */
    public function availableTools(User $user, callable $tokenCan): array
    {
        return array_values(array_filter(
            $this->tools,
            fn (McpTool $tool) => $this->isAvailable($tool, $user, $tokenCan),
        ));
    }

    protected function isAvailable(McpTool $tool, User $user, callable $tokenCan): bool
    {
        if ($tool->requiredAbility() !== null && ! $tokenCan($tool->requiredAbility())) {
            return false;
        }

        if ($tool->requiredModule() !== null && ! AppModules::isVisible($user, $tool->requiredModule())) {
            return false;
        }

        return true;
    }

    /**
     * @param  callable(string): bool  $tokenCan
     * @return list<array{name: string, description: string, inputSchema: array, annotations: array}>
     */
    public function listToolDefinitions(User $user, callable $tokenCan): array
    {
        return array_map(fn (McpTool $tool) => [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'inputSchema' => $tool->inputSchema(),
            'annotations' => $tool->annotations(),
        ], $this->availableTools($user, $tokenCan));
    }

    /**
     * @param  callable(string): bool  $tokenCan
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     *
     * @throws McpUnknownToolException when $name doesn't exist OR isn't
     *                                 available to this user/token — deliberately the same error either way.
     */
    public function call(User $user, callable $tokenCan, string $name, array $arguments): array
    {
        foreach ($this->availableTools($user, $tokenCan) as $tool) {
            if ($tool->name() === $name) {
                return $tool->handle($user, $arguments);
            }
        }

        throw new McpUnknownToolException($name);
    }

    /**
     * Every registered tool, regardless of availability for any particular
     * user/token — reference documentation only (/docs/mcp). Never used to
     * decide what tools/list or tools/call actually expose.
     *
     * @return list<array{name: string, description: string, inputSchema: array, annotations: array, requiredAbility: ?string, requiredModule: ?string}>
     */
    public function allToolDefinitions(): array
    {
        return array_map(fn (McpTool $tool) => [
            'name' => $tool->name(),
            'description' => $tool->description(),
            'inputSchema' => $tool->inputSchema(),
            'annotations' => $tool->annotations(),
            'requiredAbility' => $tool->requiredAbility(),
            'requiredModule' => $tool->requiredModule(),
        ], $this->tools);
    }
}
