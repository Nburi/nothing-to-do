<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Task */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'list' => $this->list,
            'project_id' => $this->project_id,
            'is_today' => $this->is_today,
            'is_important' => $this->is_important,
            'deadline' => $this->deadline?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'effective_date' => $this->effectiveDate()?->toDateString(),
            'effective_date_label' => $this->effectiveDateLabel(),
            'is_overdue' => $this->isOverdue(),
            'is_urgent' => $this->isUrgent(),
            'is_completed' => $this->is_completed,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
