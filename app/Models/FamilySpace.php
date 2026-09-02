<?php

namespace App\Models;

use App\Livewire\Support\FamilyColors;
use Database\Factories\FamilySpaceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A shared household task list — several accounts, one list of chores/
 * errands, each member visually distinguished by a small fixed color (see
 * FamilyColors). Membership shape (invite code, no approval step, owner
 * handover on leave) is deliberately copied from AgendaSpace — see
 * CLAUDE.md, "Familie — geteilte Aufgaben", for why this is a standalone
 * feature next to the personal board rather than a ListConcepts entry.
 */
class FamilySpace extends Model
{
    /** @use HasFactory<FamilySpaceFactory> */
    use HasFactory;

    /** Invite-code alphabet, minus characters people mistype off a screen: O/0, I/1. */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const CODE_LENGTH = 6;

    protected $fillable = [
        'owner_id',
        'name',
        'invite_code',
    ];

    public static function generateInviteCode(): string
    {
        do {
            $code = '';

            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (static::where('invite_code', $code)->exists());

        return $code;
    }

    public static function findByInviteCode(string $code): ?self
    {
        $normalised = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');

        return $normalised === ''
            ? null
            : static::where('invite_code', $normalised)->first();
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    /** The longest-standing other member — who'd inherit ownership if the owner left right now. */
    public function nextOwnerCandidate(?int $excludingUserId = null): ?User
    {
        return $this->members()
            ->when($excludingUserId !== null, fn (Builder $q) => $q->whereKeyNot($excludingUserId))
            ->orderBy('family_space_user.created_at')
            ->orderBy('users.id')
            ->first();
    }

    public function inviteUrl(): string
    {
        return route('family.join', ['code' => $this->invite_code]);
    }

    /**
     * The first unused color in the fixed palette — what a fresh join/create
     * gets by default. Wraps around (reusing a color) once a family has more
     * members than the palette has colors; a rare edge case for a household,
     * accepted rather than growing the palette further or erroring.
     */
    public function nextAvailableColor(): string
    {
        $used = $this->members()->pluck('family_space_user.color')->filter()->all();

        foreach (FamilyColors::KEYS as $key) {
            if (! in_array($key, $used, true)) {
                return $key;
            }
        }

        return FamilyColors::KEYS[count($used) % count(FamilyColors::KEYS)];
    }

    /** This member's card color in this space, or null if they aren't a member. */
    public function colorFor(User $user): ?string
    {
        return $this->members()->whereKey($user->id)->value('family_space_user.color');
    }

    // ── Relations ─────────────────────────────────────────────────────

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('color')->withTimestamps();
    }

    /** @return HasMany<FamilyTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(FamilyTask::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopeForMember(Builder $query, User $user): Builder
    {
        return $query->whereHas('members', fn (Builder $q) => $q->whereKey($user->id));
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('name');
    }

    public function shortName(): string
    {
        return Str::limit($this->name, 18, '…');
    }
}
