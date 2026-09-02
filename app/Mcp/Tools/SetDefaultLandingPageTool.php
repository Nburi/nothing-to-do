<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\User;
use App\Services\AppModules;

class SetDefaultLandingPageTool extends McpTool
{
    public function name(): string
    {
        return 'set_default_landing_page';
    }

    public function description(): string
    {
        return 'Set which page opens by default ("app" for the board, or a currently-visible module key). '
            .'Call get_settings first to see which pages are valid right now.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'page' => ['type' => 'string'],
            ],
            'required' => ['page'],
        ];
    }

    public function requiredAbility(): ?string
    {
        return McpAbility::WRITE;
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $page = $arguments['page'] ?? null;

        if (! is_string($page) || ! AppModules::isValidLandingPage($user, $page)) {
            $options = implode(', ', array_column(AppModules::landingPageOptions($user), 'key'));

            throw new McpToolExecutionException("Not a currently valid landing page. Valid options right now: {$options}");
        }

        $user->update(['default_page' => $page]);

        return ['default_page' => $page];
    }
}
