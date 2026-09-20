<?php

namespace App\Mcp\Servers;

use App\Mcp\McpServer;
use App\Mcp\McpTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * The MCP server AI clients connect to over OAuth at POST /mcp
 * (routes/ai.php). Its tools are the app's own App\Mcp\Tools\* registry,
 * wrapped one-for-one by BridgedTool — this class holds no tool logic of its
 * own, it only hands Laravel MCP the same registry the Sanctum endpoint uses.
 */
#[Name('nothing-to-do')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
Read and organize this user's tasks, projects, task groups, agenda, categories, progress and
settings in nothing-to-do. Which tools you see adapts live to what this user has enabled in
Settings and to what this connection is allowed to do — an absent tool means "not available
right now", not a bug.
MARKDOWN)]
class NothingToDoServer extends Server
{
    public function __construct(
        \Laravel\Mcp\Server\Contracts\Transport $transport,
        protected McpServer $registry,
    ) {
        parent::__construct($transport);
    }

    /**
     * Laravel MCP paginates tools/list at 15 per page by default — one page
     * short of this server's 19 tools, which quietly pushed the last few
     * (delete_task among them) onto a second page behind a nextCursor. A
     * spec-compliant client follows that cursor, but there is nothing to gain
     * from making every client do a second round trip for a list this small,
     * and plenty to lose if one doesn't. One page, always.
     */
    public int $defaultPaginationLength = 100;

    public int $maxPaginationLength = 100;

    protected function boot(): void
    {
        $this->tools = array_map(
            fn (McpTool $tool): BridgedTool => new BridgedTool($tool, $this->registry),
            $this->registry->tools(),
        );
    }
}
