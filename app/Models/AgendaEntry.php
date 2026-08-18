<?php

namespace App\Models;

use Database\Factories\AgendaEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

class AgendaEntry extends Model
{
    /** @use HasFactory<AgendaEntryFactory> */
    use HasFactory;

    public const TYPES = [
        'homework' => 'Hausaufgabe',
        'exam' => 'Prüfung',
    ];

    /** How many weekdays ahead the dashboard homework preview looks — weekends don't count as lookahead. */
    public const DASHBOARD_PREVIEW_WEEKDAYS = 3;

    /** Word count for the one-line note snippet shown on the entry row, mirroring Task::NOTES_PREVIEW_WORDS. */
    public const NOTES_PREVIEW_WORDS = 8;

    protected $fillable = [
        'type',
        'subject',
        'title',
        'notes',
        'date',
        'agenda_space_id',
    ];

    /**
     * `is_done` is deliberately absent from both lists: "done" moved to the
     * `agenda_entry_completions` pivot when entries became shareable, since a
     * class entry is ticked off per person. The column still exists (it is
     * dropped in a follow-up commit, see TODO.md) and leaving it unfillable and
     * uncast makes an accidental write to the dead column fail loudly.
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /** The authenticated user's local calendar day, falling back to the server clock. */
    private static function today(): Carbon
    {
        return auth()->user()?->localToday() ?? Carbon::today();
    }

    public function isOverdue(): bool
    {
        return ! $this->isDoneForCurrentUser() && $this->date->lessThan(self::today());
    }

    /** True when this entry belongs to a shared space rather than one person. */
    public function isShared(): bool
    {
        return $this->agenda_space_id !== null;
    }

    /**
     * Has this person ticked it off? Prefers the `done_for_me` flag that
     * `scopeWithCompletionState()` selects alongside the row, so rendering a
     * list costs one query rather than one per entry; falls back to asking the
     * database when the entry was loaded without it.
     */
    public function isDoneFor(User $user): bool
    {
        if (array_key_exists('done_for_me', $this->attributes)) {
            return (bool) $this->attributes['done_for_me'];
        }

        return $this->completedBy()->whereKey($user->id)->exists();
    }

    private function isDoneForCurrentUser(): bool
    {
        $user = auth()->user();

        return $user !== null && $this->isDoneFor($user);
    }

    /**
     * How many members of the space have finished this — the "5 von 22" counter.
     * Null for a private entry, where the number would only ever be 0 or 1.
     */
    public function completedCount(): ?int
    {
        return $this->isShared() ? (int) ($this->completed_by_count ?? $this->completedBy()->count()) : null;
    }

    /** Toggle this person's own completion. Never touches anyone else's. */
    public function toggleDoneFor(User $user): void
    {
        $this->completedBy()->toggle($user->id);
    }

    /** Short human label for the date: heute / morgen / weekday / d.m. / überfällig. */
    public function dateLabel(): string
    {
        $today = self::today();

        if ($this->date->lessThan($today)) {
            return 'überfällig';
        }

        // Carbon 3 returns a float; cast for exact day-bucket matching.
        $days = (int) $today->diffInDays($this->date);

        return match (true) {
            $days === 0 => 'heute',
            $days === 1 => 'morgen',
            $days <= 6 => ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][$this->date->dayOfWeek],
            default => $this->date->format('d.m.'),
        };
    }

    /** One-line snippet of the note for the entry row, truncated by word count. Plain text — Agenda notes carry no Markdown. */
    public function notesPreview(int $words = self::NOTES_PREVIEW_WORDS): ?string
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $this->notes));

        if ($text === '') {
            return null;
        }

        $parts = explode(' ', $text);
        $preview = implode(' ', array_slice($parts, 0, $words));

        return count($parts) > $words ? $preview.'…' : $preview;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(AgendaSpace::class, 'agenda_space_id');
    }

    /** Everyone who has ticked this entry off — see the completions migration. */
    public function completedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'agenda_entry_completions')->withTimestamps();
    }

    /**
     * Open homework (own or a shared class's) due within the next
     * DASHBOARD_PREVIEW_WEEKDAYS *weekdays* — Saturday/Sunday are skipped
     * when counting the lookahead, so a Thursday/Friday "today" still reaches
     * into the following Monday/Tuesday rather than being pushed further out
     * by the weekend. `addWeekdays()` starts counting from tomorrow, so an
     * overdue or due-today item is always included too (its date is already
     * on or before the cutoff) — deliberate, since those are the most urgent.
     *
     * @return Collection<int, self>
     */
    public static function homeworkPreviewFor(User $user): Collection
    {
        $cutoff = $user->localToday()->copy()->addWeekdays(self::DASHBOARD_PREVIEW_WEEKDAYS);

        return static::query()
            ->visibleTo($user)
            ->ofType('homework')
            ->openFor($user)
            ->where('date', '<=', $cutoff)
            ->ordered()
            ->get();
    }

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /**
     * Everything this person is allowed to see: their own private entries, plus
     * every entry in a space they belong to. This is the *only* gate — the
     * Agenda component resolves every single entry through it rather than
     * trusting an id from the frontend, so another user's private entry and a
     * space you never joined are equally invisible.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where(function (Builder $own) use ($user) {
                $own->where('user_id', $user->id)->whereNull('agenda_space_id');
            })->orWhereIn(
                'agenda_space_id',
                AgendaSpace::query()->forMember($user)->select('agenda_spaces.id')
            );
        });
    }

    /** Restrict to one shared space, or — with null — to private entries only. */
    public function scopeInSpace(Builder $query, ?int $spaceId): Builder
    {
        return $spaceId === null
            ? $query->whereNull('agenda_space_id')
            : $query->where('agenda_space_id', $spaceId);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /** Still open *for this person* — someone else's tick doesn't hide it. */
    public function scopeOpenFor(Builder $query, User $user): Builder
    {
        return $query->whereDoesntHave('completedBy', fn (Builder $q) => $q->whereKey($user->id));
    }

    public function scopeDoneFor(Builder $query, User $user): Builder
    {
        return $query->whereHas('completedBy', fn (Builder $q) => $q->whereKey($user->id));
    }

    /**
     * Select the two completion facts a row needs — "have I finished it" and
     * "how many others have" — as part of the same query, so a list of 40
     * entries doesn't fire 80 follow-ups.
     */
    public function scopeWithCompletionState(Builder $query, User $user): Builder
    {
        return $query
            ->withCount('completedBy')
            ->withExists(['completedBy as done_for_me' => fn (Builder $q) => $q->whereKey($user->id)]);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('date')->orderBy('created_at');
    }
}
