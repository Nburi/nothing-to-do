<?php

namespace App\Mcp\Tools\Admin;

use App\Mcp\McpAbility;
use App\Models\User;

class UpdateAnnouncementTool extends AdminTool
{
    use BuildsAnnouncementAttributes;

    private const EDITABLE = [
        'title', 'description', 'type', 'link_type', 'related_module', 'only_for_module_users',
        'external_url', 'external_link_label', 'highlight_selector',
    ];

    public function name(): string
    {
        return 'update_announcement';
    }

    public function description(): string
    {
        return 'ADMIN: edit a DRAFT announcement. Only the fields you pass change. Refused for already-published '
            .'announcements — editing those would change what users currently see; the admin must unpublish '
            .'first. Cannot publish.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string', 'description' => 'Max 500 characters.'],
            ] + $this->announcementSchemaProperties(),
            'required' => ['id'],
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
        $announcement = $this->announcement($arguments['id'] ?? null);
        $this->publishedGuard($announcement->is_published, 'announcement', 'edited');

        $existingLink = $announcement->related_module !== null ? 'module'
            : ($announcement->external_url !== null ? 'external' : 'none');

        $effective = array_merge([
            'title' => $announcement->title,
            'description' => $announcement->description,
            'type' => $announcement->type,
            'link_type' => $existingLink,
            'related_module' => $announcement->related_module,
            'only_for_module_users' => $announcement->only_for_module_users,
            'external_url' => $announcement->external_url,
            'external_link_label' => $announcement->external_link_label,
            'highlight_selector' => $announcement->highlight_selector,
        ], array_intersect_key($arguments, array_flip(self::EDITABLE)));

        $announcement->update($this->announcementAttributes($effective));

        return $this->announcementResult($announcement->fresh());
    }
}
