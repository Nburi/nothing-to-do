<?php

namespace App\Http\Resources;

use App\Models\EventCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EventCategory */
class EventCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'color' => $this->color,
            'pomodoro_enabled' => $this->pomodoro_enabled,
            'sort_order' => $this->sort_order,
            // What the category's Pomodoro focus sessions suggest (Kategorie-Aufgaben-Verknüpfung).
            // task_source is null (no link) or one of tasks|project|group|agenda_entry|agenda_generic|text.
            'task_source' => $this->task_source,
            'task_source_label' => $this->taskSourceLabel(),
            'linked_project_id' => $this->linked_project_id,
            'linked_group_id' => $this->linked_group_id,
            'linked_agenda_entry_id' => $this->linked_agenda_entry_id,
            'linked_text' => $this->linked_text,
            'pinned_task_ids' => $this->whenLoaded('pinnedTasks', fn () => $this->pinnedTasks->pluck('id')->all()),
        ];
    }
}
