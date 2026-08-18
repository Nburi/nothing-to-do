<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Who is actively creating or editing an entry in a shared class right now — a
 * presence-shaped signal read through a TTL (see User::isOnline() for the same
 * pattern), not a durable record. One row per user: a person can only have one
 * form open at a time, and a second tab overwriting the first is the same
 * simplification `users.last_seen_at` already makes.
 */
class AgendaDraft extends Model
{
    /**
     * How long a draft is trusted without a fresh write. Considerably shorter
     * than User::PRESENCE_TTL_SECONDS: typing a Fach takes seconds, not
     * minutes, and an open form re-syncs itself every 8s (Agenda::heartbeatDraft(),
     * driven by the agenda page's own wire:poll — see agenda.blade.php). 2.5x
     * the poll interval, the same headroom ratio User::PRESENCE_TTL_SECONDS
     * keeps over its own 60s heartbeat, so one slow/missed tick doesn't flicker
     * the banner off.
     */
    public const TTL_SECONDS = 20;

    protected $fillable = [
        'user_id',
        'agenda_space_id',
        'agenda_entry_id',
        'type',
        'subject',
    ];

    // ── Relations ─────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(AgendaSpace::class, 'agenda_space_id');
    }

    /** Set while editing an existing entry; null while creating a new one. */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(AgendaEntry::class, 'agenda_entry_id');
    }

    // ── Writing ───────────────────────────────────────────────────────

    /**
     * Record (or move) this user's active draft — also the open form's own
     * heartbeat (Agenda::heartbeatDraft()), which just calls this again with
     * the same values to refresh the TTL. Silently clears instead for a
     * private entry (`$spaceId === null`,
     * nobody else could ever see it), for someone who has opted out of presence
     * entirely (same gate as `User::touchPresence()` — turning it off stops the
     * recording, not just the display), or for a space/type that doesn't
     * actually belong to this user: `$spaceId`/`$type` reach here straight off
     * Livewire properties, which a client can set directly, bypassing the
     * buttons that normally drive them.
     */
    public static function syncFor(User $user, ?int $spaceId, ?int $entryId, string $type, string $subject): void
    {
        if ($spaceId === null || ! $user->show_presence || ! array_key_exists($type, AgendaEntry::TYPES)) {
            self::clearFor($user);

            return;
        }

        if (! $user->agendaSpaces()->whereKey($spaceId)->exists()) {
            self::clearFor($user);

            return;
        }

        $draft = self::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'agenda_space_id' => $spaceId,
                'agenda_entry_id' => $entryId,
                'type' => $type,
                'subject' => trim($subject),
            ]
        );

        // The heartbeat re-syncs identical values on purpose, just to refresh
        // the TTL — and updateOrCreate() only issues an UPDATE when something is
        // actually dirty, so an unchanged draft would otherwise never bump
        // updated_at at all. touch() bumps it unconditionally.
        $draft->touch();
    }

    public static function clearFor(User $user): void
    {
        self::query()->where('user_id', $user->id)->delete();
    }

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('updated_at', '>', Carbon::now()->subSeconds(self::TTL_SECONDS));
    }

    public function scopeExcluding(Builder $query, User $user): Builder
    {
        return $query->where('user_id', '!=', $user->id);
    }
}
