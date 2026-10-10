<?php

namespace App\Mcp\Tools;

use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Models\AgendaEntry;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

/**
 * Creates a PRIVATE Agenda entry only — deliberately no agenda_space_id
 * parameter. Posting into a shared class space is a small "visible to other
 * people" action (CLAUDE.md, "Agenda — Klassen teilen"), and letting an AI
 * agent do that on the user's behalf without them ever seeing it first felt
 * like the wrong default for a v1. Sharing an entry afterwards is a normal
 * edit in the app itself.
 */
class CreateAgendaEntryTool extends McpTool
{
    public function name(): string
    {
        return 'create_agenda_entry';
    }

    public function description(): string
    {
        return 'Add a private Agenda entry (homework or exam) for this user. Always private — this tool '
            .'never posts into a shared class space, even if the user belongs to one.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string', 'enum' => array_keys(AgendaEntry::TYPES)],
                'subject' => ['type' => 'string', 'description' => 'e.g. "Mathematik". Free text.'],
                'title' => ['type' => 'string'],
                'date' => ['type' => 'string', 'format' => 'date'],
                'duration_minutes' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 600, 'description' => 'Estimated work time in minutes. Homework only — ignored for exams.'],
                'notes' => ['type' => 'string', 'description' => 'Plain text, max 2000 characters.'],
            ],
            'required' => ['type', 'subject', 'title', 'date'],
        ];
    }

    public function requiredAbility(): ?string
    {
        return McpAbility::WRITE;
    }

    public function requiredModule(): ?string
    {
        return 'agenda';
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false];
    }

    public function handle(User $user, array $arguments): array
    {
        $data = Validator::make($arguments, [
            'type' => ['required', 'string', 'in:'.implode(',', array_keys(AgendaEntry::TYPES))],
            'subject' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:600'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ])->validate();

        $notes = isset($data['notes']) ? trim($data['notes']) : '';

        $entry = $user->agendaEntries()->create([
            'type' => $data['type'],
            'subject' => trim($data['subject']),
            'title' => trim($data['title']),
            'date' => $data['date'],
            'duration_minutes' => $data['type'] === 'homework' ? ($data['duration_minutes'] ?? null) : null,
            'notes' => $notes !== '' ? $notes : null,
            'agenda_space_id' => null,
        ]);

        return [
            'id' => $entry->id,
            'type' => $entry->type,
            'subject' => $entry->subject,
            'title' => $entry->title,
            'date' => $entry->date->toDateString(),
            'duration_minutes' => $entry->duration_minutes,
            'is_shared' => false,
        ];
    }
}
