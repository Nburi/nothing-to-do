<?php

namespace App\Http\Resources;

use App\Models\AgendaEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One Agenda entry as seen by the token's own user: `is_done` is *their* completion (on a shared
 * class entry every member has their own), and `completed_count` is how many members have finished
 * it (null for a private entry, where the number would only ever be 0 or 1).
 *
 * @mixin AgendaEntry
 */
class AgendaEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'type' => $this->type,
            'subject' => $this->subject,
            'title' => $this->title,
            'notes' => $this->notes,
            'date' => $this->date->toDateString(),
            'date_label' => $this->dateLabel(),
            'is_overdue' => $this->isOverdue(),
            'duration_minutes' => $this->duration_minutes,
            'is_done' => $this->isDoneFor($user),
            'agenda_space_id' => $this->agenda_space_id,
            'agenda_space_name' => $this->whenLoaded('space', fn () => $this->space?->name),
            'is_shared' => $this->isShared(),
            'completed_count' => $this->completedCount(),
            'is_mine' => $this->user_id === $user->id,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
