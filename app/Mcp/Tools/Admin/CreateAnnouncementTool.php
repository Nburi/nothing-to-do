<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\McpAbility;
use App\Models\User;

class CreateAnnouncementTool extends AdminTool
{
    use BuildsAnnouncementAttributes;

    public function name(): string
    {
        return 'create_announcement';
    }

    public function description(): string
    {
        return 'ADMIN: create a feature announcement ("what\'s new" toast) as a DRAFT. It is never published '
            .'through MCP — the admin previews and publishes it in the admin area. German and short: title max 255, '
            .'description max 500 characters.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string', 'description' => 'Max 500 characters.'],
            ] + $this->announcementSchemaProperties(),
            'required' => ['title', 'description'],
        ];
    }

    public function requiredAbility(): ?string
    {
        return McpAbility::WRITE;
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false];
    }

    public function handle(User $user, array $arguments): array
    {
        $arguments += [
            'type' => 'info',
            'link_type' => isset($arguments['related_module']) ? 'module' : (isset($arguments['external_url']) ? 'external' : 'none'),
        ];

        $announcement = $user->createdAnnouncements()->create(
            $this->announcementAttributes($arguments) + ['is_published' => false],
        );

        return $this->announcementResult($announcement->fresh());
    }
}
