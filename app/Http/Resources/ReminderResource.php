<?php

namespace App\Http\Resources;

use App\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Reminder */
class ReminderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user() ?? $this->user;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'remind_on' => $this->remind_on->toDateString(),
            'date_label' => $user ? $this->dateLabel($user->localToday()) : null,
            'notify_evening_before' => $this->notify_evening_before,
            'notify_on_day' => $this->notify_on_day,
            'acknowledged' => $this->isAcknowledged(),
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
