<?php

namespace App\Livewire\Concerns;

use App\Models\AgendaSpace;
use App\Models\User;
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

    /**
     * Null = the sheet lists the user's classes; a space id = the sheet is
     * showing that class's members. One sheet, two views, rather than a second
     * modal stacked on the first.
     */
    public ?int $managingSpaceId = null;

    /** @return Collection<int, AgendaSpace> */
    #[Computed]
    public function spaces(): Collection
    {
        return auth()->user()->agendaSpaces()
            // Members are eager-loaded because each row shows an online count,
            // which is a TTL comparison per member rather than something SQL can
            // count for us — without this it would be a query per class.
            ->with('members:id,name,last_seen_at,show_presence')
            ->withCount('members')
            ->ordered()
            ->get();
    }

    /** The class whose members the sheet is currently showing, if any. */
    #[Computed]
    public function managedSpace(): ?AgendaSpace
    {
        if ($this->managingSpaceId === null) {
            return null;
        }

        return auth()->user()->agendaSpaces()->find($this->managingSpaceId);
    }

    /**
     * That class's members, online first and then alphabetically — "who is here
     * right now" is the question this list exists to answer, so it should not
     * need scanning. Sorted in PHP rather than SQL because "online" is a
     * computed comparison against a TTL, not a column.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function managedMembers(): Collection
    {
        $space = $this->managedSpace;

        if ($space === null) {
            return collect();
        }

        return $space->members()
            ->get()
            ->sortBy([
                fn (User $a, User $b) => ($b->isOnline() <=> $a->isOnline()),
                fn (User $a, User $b) => mb_strtolower($a->name) <=> mb_strtolower($b->name),
            ])
            ->values();
    }

    /** How many members of a class are online right now. */
    public function onlineCountFor(AgendaSpace $space): int
    {
        return $space->members->filter(fn (User $member) => $member->isOnline())->count();
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
        $this->managingSpaceId = null;
        $this->resetValidation();
        $this->showSpaces = true;
    }

    public function closeSpaces(): void
    {
        $this->showSpaces = false;
        $this->managingSpaceId = null;
    }

    // ── Members ───────────────────────────────────────────────────────

    /** Switch the sheet over to one class's member list. */
    public function openMembers(int $spaceId): void
    {
        // Resolved through the user's own memberships, so an id for a class this
        // user isn't in never even opens the view.
        $this->managingSpaceId = $this->memberSpace($spaceId)->id;

        unset($this->managedSpace, $this->managedMembers);
    }

    /** Back to the list of classes. */
    public function closeMembers(): void
    {
        $this->managingSpaceId = null;
    }

    /**
     * Remove someone from a class. Owner-only, unlike editing entries — a member
     * correcting a typo is routine, one classmate throwing another out of the
     * shared agenda is not.
     *
     * Non-destructive in the same way leaving is: entries that person wrote stay
     * with the class they wrote them for. Their own completions go with them,
     * since a completion only means anything to the person who ticked it.
     */
    public function removeMember(int $spaceId, int $userId): void
    {
        $space = $this->ownedSpace($spaceId);

        // The owner leaves via "Verlassen", which hands ownership over properly;
        // removing yourself here would leave the class without an owner.
        if ($userId === $space->owner_id) {
            return;
        }

        // Resolved through the class's own membership, like every other id in
        // this app — someone who isn't in it 404s rather than silently no-op'ing.
        $member = $space->members()->findOrFail($userId);

        $space->members()->detach($member->id);

        unset($this->spaces, $this->managedSpace, $this->managedMembers);
    }

    /**
     * Hand the class over to another member. Without this the only way to change
     * owner would be for the owner to leave, which is a strange thing to have to
     * do just to pass on the admin role.
     */
    public function transferOwnership(int $spaceId, int $userId): void
    {
        $space = $this->ownedSpace($spaceId);

        $member = $space->members()->findOrFail($userId);

        $space->update(['owner_id' => $member->id]);

        unset($this->spaces, $this->managedSpace, $this->managedMembers);
    }

    /** A space this user owns. Anything else 404s rather than silently no-op'ing. */
    protected function ownedSpace(int $id): AgendaSpace
    {
        return auth()->user()->ownedAgendaSpaces()->findOrFail($id);
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

        $successor = $space->nextOwnerCandidate();

        if ($successor === null) {
            $space->delete();
        } elseif ($space->owner_id === $userId) {
            $space->update(['owner_id' => $successor->id]);
        }

        $this->leaveMemberViewIf($id);

        unset($this->spaces);
    }

    /**
     * Delete a space for everyone. Owner only — this is the one action a plain
     * member may not take, unlike editing entries, which every member may.
     */
    public function deleteSpace(int $id): void
    {
        $this->ownedSpace($id)->delete();

        $this->leaveMemberViewIf($id);

        unset($this->spaces);
    }

    /**
     * Drop back to the class list when the class currently being managed is the
     * one that just disappeared — otherwise the sheet would sit on a member view
     * for a space that no longer exists.
     */
    private function leaveMemberViewIf(int $spaceId): void
    {
        if ($this->managingSpaceId === $spaceId) {
            $this->managingSpaceId = null;
        }

        unset($this->managedSpace, $this->managedMembers);
    }

    /** Roll a fresh invite code, invalidating any link already handed out. */
    public function regenerateInviteCode(int $id): void
    {
        $this->memberSpace($id)->update(['invite_code' => AgendaSpace::generateInviteCode()]);

        unset($this->spaces);
    }
}
