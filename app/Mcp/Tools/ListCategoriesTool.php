<?php

namespace App\Mcp\Tools;

use App\Mcp\McpTool;
use App\Models\User;

class ListCategoriesTool extends McpTool
{
    public function name(): string
    {
        return 'list_categories';
    }

    public function description(): string
    {
        return 'List this user\'s schedule categories (e.g. "Training", "Schule") — name, colour, whether '
            .'a Pomodoro focus timer is enabled for it, and what task source it\'s linked to, if any.';
    }

    public function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => (object) []];
    }

    public function requiredModule(): ?string
    {
        return 'schedule';
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $categories = $user->eventCategories()->ordered()->with(['customAttributes', 'pinnedTasks'])->get();

        return [
            'categories' => $categories->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'color' => $category->color,
                'pomodoro_enabled' => $category->pomodoro_enabled,
                'task_source' => $category->task_source,
                'task_source_label' => $category->taskSourceLabel(),
                'linked_project_id' => $category->linked_project_id,
                'linked_group_id' => $category->linked_group_id,
                'linked_agenda_entry_id' => $category->linked_agenda_entry_id,
                'linked_text' => $category->linked_text,
                'pinned_task_ids' => $category->pinnedTasks->pluck('id')->all(),
                'attributes' => $category->customAttributes->map(fn ($attr) => [
                    'id' => $attr->id,
                    'name' => $attr->name,
                    'type' => $attr->type,
                    'unit' => $attr->unit,
                    'options' => $attr->optionsList(),
                ])->values()->all(),
            ])->values()->all(),
            'pomodoro_rhythm' => $user->pomodoro(),
            'pomodoro_autostart' => (bool) $user->pomodoro_autostart,
        ];
    }
}
