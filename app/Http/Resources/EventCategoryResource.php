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
            'area_ids' => $this->areas->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'sort_order' => $this->sort_order,
        ];
    }
}
