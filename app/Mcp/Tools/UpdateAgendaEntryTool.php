<?php

namespace App\Mcp\Tools;

use App\Mcp\Exceptions\McpToolExecutionException;
use App\Mcp\McpAbility;
use App\Mcp\McpTool;
use App\Mcp\Tools\Concerns\ResolvesOwnedModel;
use App\Models\AgendaEntry;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Same rules as the app: any member may edit what their class posted, `is_done`
 * is per person, and the entry can't be moved into or out of a class here
 * (sharing stays a deliberate step in the app itself).
 */
class UpdateAgendaEntryTool extends McpTool
{
    use ResolvesOwnedModel;

    public function name(): string
    {
        return 'update_agenda_entry';
    }

    public function description(): string
    {
        return 'Update an Agenda entry (homework or exam). Only the fields you pass are changed. is_done marks '
            .'it finished for THIS user only. private_note is the user\'s own note on an entry shared with a '
            .'class (only they see it; "" removes it). Editing a class entry changes it for the whole class. '
            .'Moving an entry between "private" and a class is not possible here.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'type' => ['type' => 'string', 'enum' => array_keys(AgendaEntry::TYPES)],
                'subject' => ['type' => 'string', 'description' => 'Max 100 characters.'],
                'title' => ['type' => 'string'],
                'date' => ['type' => 'string', 'format' => 'date'],
                'duration_minutes' => ['type' => ['integer', 'null'], 'minimum' => 1, 'maximum' => 600, 'description' => 'Homework only; null clears it.'],
                'notes' => ['type' => ['string', 'null'], 'description' => 'Shared plain-text note, max 2000 characters.'],
                'private_note' => ['type' => ['string', 'null'], 'description' => 'Only for entries shared with a class. Max 2000 characters.'],
                'is_done' => ['type' => 'boolean'],
            ],
            'required' => ['id'],
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
        return ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true];
    }

    public function handle(User $user, array $arguments): array
    {
        $entry = $this->visibleAgendaEntry($user, $arguments['id'] ?? null);

        $data = Validator::make($arguments, [
            'type' => ['sometimes', Rule::in(array_keys(AgendaEntry::TYPES))],
            'subject' => ['sometimes', 'string', 'max:100'],
            'title' => ['sometimes', 'string', 'max:255'],
            'date' => ['sometimes', 'date'],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:600'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'private_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_done' => ['sometimes', 'boolean'],
        ])->validate();

        if (array_key_exists('private_note', $data) && ! $entry->isShared()) {
            throw new McpToolExecutionException(
                'private_note only exists on entries shared with a class — for a private entry use notes.'
            );
        }

        $updates = [];
        foreach (['type', 'date', 'duration_minutes'] as $key) {
            if (array_key_exists($key, $data)) {
                $updates[$key] = $data[$key];
            }
        }
        foreach (['subject', 'title'] as $key) {
            if (array_key_exists($key, $data)) {
                $updates[$key] = trim($data[$key]);
            }
        }
        if (array_key_exists('notes', $data)) {
            $notes = trim((string) $data['notes']);
            $updates['notes'] = $notes !== '' ? $notes : null;
        }

        // A duration only exists for homework (same rule as the form and the REST API).
        if (($updates['type'] ?? $entry->type) === 'exam') {
            $updates['duration_minutes'] = null;
        }

        $entry->update($updates);

        if (array_key_exists('private_note', $data)) {
            $entry->setPrivateNoteFor($user, $data['private_note']);
        }

        if (array_key_exists('is_done', $data) && $data['is_done'] !== $entry->isDoneFor($user)) {
            $entry->toggleDoneFor($user);
        }

        return AgendaEntryPresenter::full($this->visibleAgendaEntry($user, $entry->id), $user);
    }
}
