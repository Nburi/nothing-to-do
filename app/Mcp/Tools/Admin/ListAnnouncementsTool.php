<?php

namespace App\Mcp\Tools\Admin;

use App\Models\FeatureAnnouncement;
use App\Models\User;

class ListAnnouncementsTool extends AdminTool
{
    public function name(): string
    {
        return 'list_announcements';
    }

    public function description(): string
    {
        return 'ADMIN: list all feature announcements (drafts and published, newest first) with their type, link '
            .'settings and publish state.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) []];
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        return [
            'announcements' => FeatureAnnouncement::orderByDesc('id')->get()
                ->map(fn (FeatureAnnouncement $a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'description' => $a->description,
                    'type' => $a->type,
                    'related_module' => $a->related_module,
                    'only_for_module_users' => $a->only_for_module_users,
                    'external_url' => $a->external_url,
                    'external_link_label' => $a->external_link_label,
                    'highlight_selector' => $a->highlight_selector,
                    'is_published' => $a->is_published,
                    'published_at' => $a->published_at?->toIso8601String(),
                ])->values()->all(),
        ];
    }
}
