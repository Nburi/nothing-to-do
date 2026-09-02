<?php

namespace App\Mcp\Tools;

use App\Mcp\McpTool;
use App\Models\AgendaEntry;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class ListAgendaEntriesTool extends McpTool
{
    public function name(): string
    {
        return 'list_agenda_entries';
    }

    public function description(): string
    {
        return 'List Agenda entries (homework and exams) this user can see — their own private entries '.
            'plus anything in a class space they belong to. Open (not yet done) entries only, by default.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => ['type' => 'string', 'enum' => array_keys(AgendaEntry::TYPES)],
                'include_done' => ['type' => 'boolean', 'description' => 'Include entries this user already finished. Default false.'],
                'limit' => ['type' => 'integer', 'description' => 'Max results, default 50, max 200.'],
            ],
        ];
    }

    public function requiredModule(): ?string
    {
        return 'agenda';
    }

    public function annotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $data = Validator::make($arguments, [
            'type' => ['sometimes', 'string', 'in:'.implode(',', array_keys(AgendaEntry::TYPES))],
            'include_done' => ['sometimes', 'boolean'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ])->validate();

        $query = AgendaEntry::query()->visibleTo($user)->withCompletionState($user)->ordered();

        if (isset($data['type'])) {
            $query->ofType($data['type']);
        }
        if (! ($data['include_done'] ?? false)) {
            $query->openFor($user);
        }

        $entries = $query->limit($data['limit'] ?? 50)->get();

        return [
            'entries' => $entries->map(fn (AgendaEntry $entry) => [
                'id' => $entry->id,
                'type' => $entry->type,
                'type_label' => $entry->typeLabel(),
                'subject' => $entry->subject,
                'title' => $entry->title,
                'date' => $entry->date->toDateString(),
                'date_label' => $entry->dateLabel(),
                'is_overdue' => $entry->isOverdue(),
                'is_shared' => $entry->isShared(),
                'is_done' => $entry->isDoneFor($user),
                'notes_preview' => $entry->notesPreview(),
            ])->values()->all(),
        ];
    }
}
