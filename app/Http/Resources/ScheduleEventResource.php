<?php

namespace App\Http\Resources;

use App\Models\ScheduleEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ScheduleEvent */
class ScheduleEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->isCategory() ? 'category' : 'appointment',
            'category_id' => $this->category_id,
            'category_name' => $this->whenLoaded('category', fn () => $this->category?->name),
            'title' => $this->displayTitle(),
            'color' => $this->colorToken(),
            'date' => $this->date->toDateString(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'duration_minutes' => $this->durationMinutes(),
            'is_recurring' => $this->template_id !== null,
            'is_cancelled' => $this->is_cancelled,
            'pomodoro_enabled' => (bool) $this->category?->pomodoro_enabled,
            'pomodoro_phase' => $this->pomodoro_phase,
            'pomodoro_cycle' => $this->pomodoro_cycle,
            'pomodoro_started_at' => $this->pomodoro_started_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
