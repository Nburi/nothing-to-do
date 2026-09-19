<?php

namespace App\Http\Resources;

use App\Models\TaskGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TaskGroup */
class TaskGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'active_count' => $this->when($this->active_count !== null, fn () => (int) $this->active_count),
            'done_count' => $this->when($this->done_count !== null, fn () => (int) $this->done_count),
            'sort_order' => $this->sort_order,
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
