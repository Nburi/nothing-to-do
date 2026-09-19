<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgendaEntryResource;
use App\Models\AgendaEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Homework & exams over the API — the same visibility and write rules as the Agenda page
 * (CLAUDE.md, "Agenda — Klassen teilen"): every entry is resolved through
 * AgendaEntry::visibleTo(), so a stranger's private entry and a class you never joined are equally
 * invisible (404), any member may edit or delete what their class posted, and a new/moved entry can
 * only be filed into a class the token's user belongs to. "Done" is per person.
 */
class AgendaEntryController extends Controller
{
    protected function visibleEntry(Request $request, int $id): AgendaEntry
    {
        return AgendaEntry::visibleTo($request->user())
            ->withCompletionState($request->user())
            ->with('space')
            ->findOrFail($id);
    }

    /** @return list<int> */
    protected function spaceIds(Request $request): array
    {
        return $request->user()->agendaSpaces()->pluck('agenda_spaces.id')->all();
    }

    /**
     * List entries. Filters: type=homework|exam, agenda_space_id=<id> (one of the user's classes),
     * private=1 (only entries that aren't shared), status=open|done|all (default open — "open" and
     * "done" are always for the token's own user).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'type' => ['sometimes', Rule::in(array_keys(AgendaEntry::TYPES))],
            'agenda_space_id' => ['sometimes', 'integer', Rule::in($this->spaceIds($request))],
            'private' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::in(['open', 'done', 'all'])],
        ]);

        $query = AgendaEntry::visibleTo($user)->withCompletionState($user)->with('space')->ordered();

        if (isset($data['type'])) {
            $query->ofType($data['type']);
        }

        if (isset($data['agenda_space_id'])) {
            $query->inSpace((int) $data['agenda_space_id']);
        } elseif ($request->boolean('private')) {
            $query->inSpace(null);
        }

        match ($data['status'] ?? 'open') {
            'open' => $query->openFor($user),
            'done' => $query->doneFor($user),
            default => null,
        };

        return AgendaEntryResource::collection($query->get())->response();
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return (new AgendaEntryResource($this->visibleEntry($request, $id)))->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(AgendaEntry::TYPES))],
            'subject' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Filing into a class you don't belong to is the whole authorization boundary for writes.
            'agenda_space_id' => ['nullable', 'integer', Rule::in($this->spaceIds($request))],
        ]);

        $notes = trim((string) ($data['notes'] ?? ''));

        $entry = $request->user()->agendaEntries()->create([
            'type' => $data['type'],
            'subject' => trim($data['subject']),
            'title' => trim($data['title']),
            'date' => $data['date'],
            // Only meaningful for homework, same as the form.
            'duration_minutes' => $data['type'] === 'homework' ? ($data['duration_minutes'] ?? null) : null,
            'notes' => $notes !== '' ? $notes : null,
            'agenda_space_id' => $data['agenda_space_id'] ?? null,
        ]);

        return (new AgendaEntryResource($this->visibleEntry($request, $entry->id)))->response()->setStatusCode(201);
    }

    /**
     * Partial update. `is_done` ticks the entry off (or back on) for the token's own user only —
     * never anyone else's completion.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $entry = $this->visibleEntry($request, $id);

        $data = $request->validate([
            'type' => ['sometimes', Rule::in(array_keys(AgendaEntry::TYPES))],
            'subject' => ['sometimes', 'string', 'max:100'],
            'title' => ['sometimes', 'string', 'max:255'],
            'date' => ['sometimes', 'date'],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:600'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'agenda_space_id' => ['sometimes', 'nullable', 'integer', Rule::in($this->spaceIds($request))],
            'is_done' => ['sometimes', 'boolean'],
        ]);

        $updates = [];

        foreach (['type', 'date', 'agenda_space_id'] as $key) {
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
        if (array_key_exists('duration_minutes', $data)) {
            $updates['duration_minutes'] = $data['duration_minutes'];
        }

        // A duration only exists for homework — switching to an exam drops it, and an exam can't take one.
        if (($updates['type'] ?? $entry->type) === 'exam') {
            $updates['duration_minutes'] = null;
        }

        $entry->update($updates);

        if (array_key_exists('is_done', $data) && $data['is_done'] !== $entry->isDoneFor($user)) {
            $entry->toggleDoneFor($user);
        }

        return (new AgendaEntryResource($this->visibleEntry($request, $entry->id)))->response();
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        AgendaEntry::visibleTo($request->user())->findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
