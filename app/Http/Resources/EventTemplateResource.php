<?php

namespace App\Http\Resources;

use App\Models\EventTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EventTemplate */
class EventTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->displayName(),
            'color' => $this->colorToken(),
            'duration' => $this->duration,
            'buffer_before' => (int) $this->buffer_before,
            'buffer_after' => (int) $this->buffer_after,
            'default_start' => $this->default_start,
            'is_recurring' => $this->is_recurring,
            'recurrence_days' => $this->recurrenceDays(),
            'sort_order' => $this->sort_order,
        ];
    }
}
