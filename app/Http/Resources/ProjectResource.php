<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Project */
class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'brainstorm' => $this->brainstorm,
            'external_url' => $this->external_url,
            'external_service_name' => $this->externalServiceName(),
            'deadline' => $this->deadline?->toDateString(),
            'deadline_label' => $this->deadline ? $this->deadlineLabel() : null,
            'is_overdue' => $this->isOverdue(),
            'is_urgent' => $this->isUrgent(),
            'done_count' => $this->when($this->done_count !== null, fn () => (int) $this->done_count),
            'sort_order' => $this->sort_order,
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            'active_tasks' => TaskResource::collection($this->whenLoaded('activeTasks')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
