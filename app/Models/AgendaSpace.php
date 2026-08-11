<?php

namespace App\Models;

use Database\Factories\AgendaSpaceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A shared agenda — a school class, or any smaller group that wants one list of
 * homework and exams. Membership is open to anyone holding the invite code;
 * there is no approval step, which is the point (a class of 22 shouldn't have
 * to wait on one person).
 */
class AgendaSpace extends Model
{
    /** @use HasFactory<AgendaSpaceFactory> */
    use HasFactory;

    /**
     * Invite-code alphabet, minus the characters people mistype when reading a
     * code off someone else's screen: O/0, I/1, and the lowercase half entirely.
     */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const CODE_LENGTH = 6;

    protected $fillable = [
        'owner_id',
        'name',
        'invite_code',
    ];

    /**
     * A fresh code that no space is using yet. Collisions are vanishingly rare
     * (32^6) but the unique index would throw rather than silently hand two
     * classes the same code, so re-roll instead of hoping.
     */
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

    /**
     * Look a space up by an invite code the user typed. Case- and
     * whitespace-insensitive, because people type "k7m 4xq" off a whiteboard.
     */
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

    public function inviteUrl(): string
    {
        return route('agenda.join', ['code' => $this->invite_code]);
    }

    // ── Relations ─────────────────────────────────────────────────────

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /** @return HasMany<AgendaEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(AgendaEntry::class);
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

    /** Short label used where the full name would overflow (row badges). */
    public function shortName(): string
    {
        return Str::limit($this->name, 18, '…');
    }
}
