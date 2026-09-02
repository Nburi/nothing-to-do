<?php

namespace App\Livewire\Concerns;

use App\Livewire\Support\FamilyColors;
use App\Models\FamilySpace;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;

/**
 * Creating, joining, leaving and color-picking for shared family task lists.
 * Shape copied from ManagesAgendaSpaces (the one place this "several
 * accounts, one invite code" problem was already solved) — deliberately a
 * separate trait rather than reusing that one, since the color-assignment
 * step has no Agenda equivalent. Shared by FamilyList (the whole "Familien
 * verwalten" sheet) and JoinFamilySpace (which only needs joinByCode).
 */
trait ManagesFamilySpaces
{
    public bool $showFamilySpaces = false;

    public string $newFamilySpaceName = '';

    public string $familyJoinCode = '';

    /** @return Collection<int, FamilySpace> */
    #[Computed]
    public function familySpaces(): Collection
    {
        return auth()->user()->familySpaces()
            ->with('members:id,name')
            ->withCount('members')
            ->ordered()
            ->get();
    }

    /**
     * A space the current user actually belongs to. Never trust a bare id
     * from the frontend — same rule as Agenda::visibleEntry() — so acting on
     * someone else's family 404s instead of leaking its name.
     */
    protected function memberFamilySpace(int $id): FamilySpace
    {
        return auth()->user()->familySpaces()->findOrFail($id);
    }

    /** A space this user owns. Anything else 404s rather than silently no-op'ing. */
    protected function ownedFamilySpace(int $id): FamilySpace
    {
        return auth()->user()->ownedFamilySpaces()->findOrFail($id);
    }

    /**
     * Join by invite code. Returns the space, or throws a validation error on
     * `familyJoinCode` if no space carries that code. Joining twice is a
     * no-op (syncWithoutDetaching, keyed on the pivot's existing color), so a
     * re-clicked invite link is harmless.
     */
    protected function joinFamilyByCode(string $code): FamilySpace
    {
        $space = FamilySpace::findByInviteCode($code);

        if ($space === null) {
            throw ValidationException::withMessages([
                'familyJoinCode' => 'Diesen Code gibt es nicht. Vertippt?',
            ]);
        }

        if (! $space->hasMember(auth()->user())) {
            $space->members()->attach(auth()->id(), ['color' => $space->nextAvailableColor()]);
        }

        return $space;
    }

    // ── Sheet ─────────────────────────────────────────────────────────

    public function openFamilySpaces(): void
    {
        $this->newFamilySpaceName = '';
        $this->familyJoinCode = '';
        $this->resetValidation();
        $this->showFamilySpaces = true;
    }

    public function closeFamilySpaces(): void
    {
        $this->showFamilySpaces = false;
    }

    public function createFamilySpace(): void
    {
        $this->newFamilySpaceName = trim($this->newFamilySpaceName);

        $this->validate([
            'newFamilySpaceName' => ['required', 'string', 'max:60'],
        ], attributes: ['newFamilySpaceName' => 'Name']);

        $space = FamilySpace::create([
            'owner_id' => auth()->id(),
            'name' => $this->newFamilySpaceName,
            'invite_code' => FamilySpace::generateInviteCode(),
        ]);

        // The creator is a member too — otherwise they'd own a family whose
        // tasks they can't see. First member always gets the first color.
        $space->members()->attach(auth()->id(), ['color' => FamilyColors::KEYS[0]]);

        $this->newFamilySpaceName = '';
        unset($this->familySpaces);
        $this->afterFamilySpaceJoinedOrCreated($space);
    }

    public function joinFamilySpace(): void
    {
        $this->validate([
            'familyJoinCode' => ['required', 'string', 'max:20'],
        ], attributes: ['familyJoinCode' => 'Code']);

        $space = $this->joinFamilyByCode($this->familyJoinCode);

        $this->familyJoinCode = '';
        unset($this->familySpaces);
        $this->afterFamilySpaceJoinedOrCreated($space);
    }

    /**
     * Leave a space. Never destructive: tasks this user created/claimed/
     * completed stay exactly as they are (nullOnDelete only ever fires on
     * real account deletion, not on leaving) — only this user's own
     * membership row (and with it, their color) disappears. If the owner
     * leaves, ownership hands to the longest-standing remaining member; the
     * last member out deletes the space.
     */
    public function leaveFamilySpace(int $id): void
    {
        $space = $this->memberFamilySpace($id);
        $userId = auth()->id();

        $space->members()->detach($userId);

        $successor = $space->nextOwnerCandidate();

        if ($successor === null) {
            $space->delete();
        } elseif ($space->owner_id === $userId) {
            $space->update(['owner_id' => $successor->id]);
        }

        $this->afterFamilySpaceGone($id);

        unset($this->familySpaces);
    }

    /** Delete a space for everyone. Owner-only, same weight as AgendaSpace's own. */
    public function deleteFamilySpace(int $id): void
    {
        $this->ownedFamilySpace($id)->delete();

        $this->afterFamilySpaceGone($id);

        unset($this->familySpaces);
    }

    /** Roll a fresh invite code, invalidating any link already handed out. */
    public function regenerateFamilyInviteCode(int $id): void
    {
        $this->memberFamilySpace($id)->update(['invite_code' => FamilySpace::generateInviteCode()]);

        unset($this->familySpaces);
    }

    /**
     * Pick a different card color within a space — validated against the
     * fixed palette so a garbage key can never be stored (see
     * partials/family-spaces-sheet.blade.php's color picker).
     */
    public function setFamilyColor(int $spaceId, string $colorKey): void
    {
        if (! in_array($colorKey, FamilyColors::KEYS, true)) {
            return;
        }

        $space = $this->memberFamilySpace($spaceId);

        $space->members()->updateExistingPivot(auth()->id(), ['color' => $colorKey]);

        unset($this->familySpaces);
    }

    /**
     * Hook for a component whose own identity depends on the space that just
     * disappeared (FamilyList's currently-viewed space) — default no-op,
     * overridden where needed, mirroring ManagesTasks::afterGroupMayHaveShrunk().
     */
    protected function afterFamilySpaceGone(int $spaceId): void
    {
        //
    }

    /**
     * Hook for a component that shows one "current" space (FamilyList) to
     * switch to the one just created/joined — default no-op (JoinFamilySpace
     * has no such concept, it always redirects straight to the board after
     * joining). Without this override, creating your very first family would
     * leave the page still showing the empty state until an extra, unasked-for
     * "Hier anzeigen" click.
     */
    protected function afterFamilySpaceJoinedOrCreated(FamilySpace $space): void
    {
        //
    }
}
