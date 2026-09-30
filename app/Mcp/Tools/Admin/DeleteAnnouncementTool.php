<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\McpAbility;
use App\Models\User;

class DeleteAnnouncementTool extends AdminTool
{
    public function name(): string
    {
        return 'delete_announcement';
    }

    public function description(): string
    {
        return 'ADMIN: PERMANENTLY delete a DRAFT announcement. Irreversible. Pass confirm_title matching its exact '
            .'current title; always confirm with the user first. Refused for published announcements.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'confirm_title' => ['type' => 'string', 'description' => 'Must exactly match the announcement\'s current title.'],
            ],
            'required' => ['id', 'confirm_title'],
        ];
    }

    public function requiredAbility(): ?string
    {
        return McpAbility::DELETE;
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => false];
    }

    public function handle(User $user, array $arguments): array
    {
        $announcement = $this->announcement($arguments['id'] ?? null);
        $this->publishedGuard($announcement->is_published, 'announcement', 'deleted');
        $this->confirmTitle($announcement->title, $arguments['confirm_title'] ?? null);

        $id = $announcement->id;
        $title = $announcement->title;
        $announcement->delete();

        return ['deleted' => true, 'id' => $id, 'title' => $title];
    }
}
