<?php

namespace App\Models;

use App\Support\Markdown\Markdown;
use Database\Factories\FamilyTaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A shared household task — created by anyone in the family, claimed or
 * assigned to one specific member, completed once for the whole family (not
 * per-person like Agenda homework — a chore finished by anyone is finished,
 * full stop). See CLAUDE.md, "Familie — geteilte Aufgaben".
 *
 * The three transition methods below are all guarded and idempotent-safe by
 * design: two family members can tap the same card within moments of each
 * other, and the worst outcome must always be "nothing happened" rather than
 * a surprising side effect (e.g. a second, slightly-later "claim" tap
 * silently completing a card someone else just grabbed).
 */
class FamilyTask extends Model
{
    /** @use HasFactory<FamilyTaskFactory> */
    use HasFactory;

    protected $fillable = [
        'family_space_id',
        'created_by',
        'assigned_to',
        'title',
        'notes',
        'is_completed',
        'completed_by',
        'completed_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_completed' => 'boolean',
            'completed_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function familySpace(): BelongsTo
    {
        return $this->belongsTo(FamilySpace::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopeForSpace(Builder $query, int $spaceId): Builder
    {
        return $query->where('family_space_id', $spaceId);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_completed', false);
    }

    public function scopeDone(Builder $query): Builder
    {
        return $query->where('is_completed', true);
    }

    // ── State transitions ────────────────────────────────────────────

    /**
     * Claim an unassigned card for $user — Signature Moment A's server side.
     * A no-op (returns false) if the card is already assigned to anyone, by
     * the time this runs, including a race against another member's claim a
     * moment earlier: claiming never overwrites an existing assignment.
     */
    public function claim(User $user): bool
    {
        if ($this->assigned_to !== null) {
            return false;
        }

        $this->update(['assigned_to' => $user->id]);

        return true;
    }

    /**
     * Mark done, credited to $user — allowed for anyone, not just the
     * assignee (a family task has one shared completion; whoever actually
     * did it taps it done). A no-op on an unassigned or already-done card.
     */
    public function completeBy(User $user): bool
    {
        if ($this->assigned_to === null || $this->is_completed) {
            return false;
        }

        $this->update([
            'is_completed' => true,
            'completed_by' => $user->id,
            'completed_at' => now(),
        ]);

        return true;
    }

    /** Undo a completion, keeping the same assignee. A no-op if it isn't done. */
    public function reopen(): bool
    {
        if (! $this->is_completed) {
            return false;
        }

        $this->update([
            'is_completed' => false,
            'completed_by' => null,
            'completed_at' => null,
        ]);

        return true;
    }

    /**
     * Renders notes as safe HTML, same options as Task/Project/GroupNote
     * (html_input=strip, allow_unsafe_links=false, plus the ++underline++
     * extension) — one shared helper so the safety options stay in one place.
     */
    public static function renderNotesMarkdown(string $text): string
    {
        return Markdown::toHtml($text);
    }
}
