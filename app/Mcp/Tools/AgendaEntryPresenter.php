<?php

namespace App\Mcp\Tools;

use App\Models\AgendaEntry;
use App\Models\User;

/** One shape for a single Agenda entry, shared by get/update so they cannot drift apart. */
final class AgendaEntryPresenter
{
    /** @return array<string, mixed> */
    public static function full(AgendaEntry $entry, User $user): array
    {
        return [
            'id' => $entry->id,
            'type' => $entry->type,
            'type_label' => $entry->typeLabel(),
            'subject' => $entry->subject,
            'title' => $entry->title,
            'notes' => $entry->notes,
            'private_note' => $entry->isShared() ? $entry->privateNoteFor($user) : null,
            'date' => $entry->date->toDateString(),
            'date_label' => $entry->dateLabel(),
            'is_overdue' => $entry->isOverdue(),
            'duration_minutes' => $entry->duration_minutes,
            'is_done' => $entry->isDoneFor($user),
            'agenda_space_id' => $entry->agenda_space_id,
            'is_shared' => $entry->isShared(),
        ];
    }
}
