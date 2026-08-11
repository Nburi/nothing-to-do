<?php

namespace App\Livewire\Concerns;

use App\Models\AgendaSpace;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;

/**
 * Creating, joining and leaving shared agendas. Shared by the Agenda page (the
 * whole "Klassen" sheet) and the invite-link landing page (which only needs
 * joinByCode) so the join rules — and the ownership handover when the owner
 * leaves — live in exactly one place.
 */
trait ManagesAgendaSpaces
{
    public bool $showSpaces = false;

    public string $newSpaceName = '';

    public string $joinCode = '';

    /** @return Collection<int, AgendaSpace> */
    #[Computed]
    public function spaces(): Collection
    {
        return auth()->user()->agendaSpaces()
            ->withCount('members')
            ->ordered()
            ->get();
    }

    /**
     * A space the current user actually belongs to. Same rule as
     * Agenda::visibleEntry() — a space id from the frontend is never trusted, so
     * acting on someone else's class 404s instead of leaking its name.
     */
    protected function memberSpace(int $id): AgendaSpace
    {
        return auth()->user()->agendaSpaces()->findOrFail($id);
    }

    /**
     * Join by invite code. Returns the space, or throws a validation error on
     * `joinCode` if no space carries that code. Joining twice is a no-op
     * (syncWithoutDetaching), so a re-clicked invite link is harmless.
     */
    protected function joinByCode(string $code): AgendaSpace
    {
        $space = AgendaSpace::findByInviteCode($code);

        if ($space === null) {
            throw ValidationException::withMessages([
                'joinCode' => 'Diesen Code gibt es nicht. Vertippt?',
            ]);
        }

        $space->members()->syncWithoutDetaching([auth()->id()]);

        return $space;
    }

    // ── Sheet ─────────────────────────────────────────────────────────

    public function openSpaces(): void
    {
        $this->newSpaceName = '';
        $this->joinCode = '';
        $this->resetValidation();
        $this->showSpaces = true;
    }

    public function closeSpaces(): void
    {
        $this->showSpaces = false;
    }

    public function createSpace(): void
    {
        $this->newSpaceName = trim($this->newSpaceName);

        $this->validate([
            'newSpaceName' => ['required', 'string', 'max:60'],
        ], attributes: ['newSpaceName' => 'Name']);

        $space = AgendaSpace::create([
            'owner_id' => auth()->id(),
            'name' => $this->newSpaceName,
            'invite_code' => AgendaSpace::generateInviteCode(),
        ]);

        // The creator is a member too — otherwise they'd own a class whose
        // entries they can't see.
        $space->members()->attach(auth()->id());

        $this->newSpaceName = '';
        unset($this->spaces);
    }

    public function joinSpace(): void
    {
        $this->validate([
            'joinCode' => ['required', 'string', 'max:20'],
        ], attributes: ['joinCode' => 'Code']);

        $this->joinByCode($this->joinCode);

        $this->joinCode = '';
        unset($this->spaces);
    }

    /**
     * Leave a space. Never destructive to the class: entries this user wrote
     * stay with the class they were written for. If the owner leaves, ownership
     * hands over to the longest-standing remaining member rather than leaving
     * the space ownerless; the last member out deletes it, at which point
     * nullOnDelete turns its entries back into private ones instead of
     * discarding them.
     */
    public function leaveSpace(int $id): void
    {
        $space = $this->memberSpace($id);
        $userId = auth()->id();

        $space->members()->detach($userId);

        $successor = $space->members()
            ->orderBy('agenda_space_user.created_at')
            ->orderBy('users.id')
            ->first();

        if ($successor === null) {
            $space->delete();
        } elseif ($space->owner_id === $userId) {
            $space->update(['owner_id' => $successor->id]);
        }

        unset($this->spaces);
    }

    /**
     * Delete a space for everyone. Owner only — this is the one action a plain
     * member may not take, unlike editing entries, which every member may.
     */
    public function deleteSpace(int $id): void
    {
        auth()->user()->ownedAgendaSpaces()->findOrFail($id)->delete();

        unset($this->spaces);
    }

    /** Roll a fresh invite code, invalidating any link already handed out. */
    public function regenerateInviteCode(int $id): void
    {
        $this->memberSpace($id)->update(['invite_code' => AgendaSpace::generateInviteCode()]);

        unset($this->spaces);
    }
}
