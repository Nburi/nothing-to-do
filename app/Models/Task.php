<?php

namespace App\Models;

use App\Planning\PlannedTask;
use App\Planning\Size;
use App\Support\Markdown\Markdown;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /** The board lists. `later` is the refactor's Later box (3-THINGS.md); the old board does not show it. */
    public const LISTS = ['inbox', 'todos', 'tasks', 'later'];

    /** Lists whose tasks are work: suggested, planned and counted as load (refactor step 1.3 / 1.4). */
    public const WORK_LISTS = ['todos', 'tasks'];

    public const LATER = 'later';

    /** Lists offered as quick-add / drag targets on the main board. */
    public const BOARD_LISTS = ['inbox', 'todos', 'tasks'];

    /** Lists that support a Today focus area. Inbox deliberately does not. */
    public const TODAY_LISTS = ['todos', 'tasks'];

    /** Urgency window in days for the soft "due soon" sort bucket. */
    public const URGENCY_DAYS = 4;

    protected $fillable = [
        'title',
        'first_step',
        'list',
        'is_today',
        'today_date',
        'is_important',
        'deadline',
        'due_date',
        'hard_deadline',
        'soft_deadline',
        'size',
        'soft_slips',
        'area_id',
        'notes',
        'duration_minutes',
        'repeat_rule',
        'repeated_from_id',
        'is_completed',
        'completed_at',
        'sort_order',
    ];

    /** Recurring-task rules, keyed by what is stored, valued by what the UI says. */
    public const REPEAT_RULES = [
        'daily' => 'Täglich',
        'weekdays' => 'Werktags',
        'weekly' => 'Wöchentlich',
        'monthly' => 'Monatlich',
    ];

    /** Words shown in the card-face notes preview before truncating with an ellipsis. */
    public const NOTES_PREVIEW_WORDS = 8;

    protected function casts(): array
    {
        return [
            'is_today' => 'boolean',
            'today_date' => 'date',
            'is_important' => 'boolean',
            'is_completed' => 'boolean',
            'deadline' => 'date',
            'due_date' => 'date',
            'hard_deadline' => 'date',
            'soft_deadline' => 'date',
            'size' => Size::class,
            'soft_slips' => 'integer',
            'duration_minutes' => 'integer',
            'completed_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Two-step rename, phase one (REFACTOR_PLAN § 3): `deadline` → `hard_deadline`
     * and `due_date` → `soft_deadline` exist side by side until the old columns
     * are dropped in a later release. Every Eloquent write mirrors whichever
     * side changed onto the other, so readers of either column see the same
     * value. When both change in one write and disagree, the new column wins.
     * (A query-builder update bypasses this; none writes these columns today —
     * keep it that way until the old columns are gone.)
     */
    protected static function booted(): void
    {
        static::saving(function (Task $task) {
            foreach (self::RENAMED_DATE_COLUMNS as $old => $new) {
                if ($task->isDirty($new)) {
                    $task->{$old} = $task->{$new};
                } elseif ($task->isDirty($old)) {
                    $task->{$new} = $task->{$old};
                }
            }

            // G2: a soft deadline the user sets again starts with a clean slate.
            // The slip rule sets the counter itself when it moves the date.
            if ($task->exists && $task->isDirty('soft_deadline') && ! $task->isDirty('soft_slips')) {
                $task->soft_slips = 0;
            }

            // Taken back out of Later: the slip rule starts over (one more roll-over, then "still want this?").
            if ($task->exists && $task->isDirty('list') && $task->getOriginal('list') === self::LATER && ! $task->isDirty('soft_slips')) {
                $task->soft_slips = 0;
            }
        });
    }

    /** Old column => new column, mirrored by booted(). */
    public const RENAMED_DATE_COLUMNS = [
        'deadline' => 'hard_deadline',
        'due_date' => 'soft_deadline',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The task's area (REQUIREMENTS § 4). Null = fits any pomodoro category that has no area links, see App\Planning\Category::accepts(). */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /** Step 4.3: the project this task is a step of (null = a loose task). */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Step 4.3: the small group this task belongs to (one list per group). */
    public function group(): BelongsTo
    {
        return $this->belongsTo(TaskGroup::class, 'group_id');
    }

    /** Step 4.3: the project stage this step belongs to (null = not in a stage). */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(ProjectStage::class, 'stage_id');
    }

    /** Step 4.3: the one task this step waits on ("wartet auf …"). */
    public function waitsOn(): BelongsTo
    {
        return $this->belongsTo(self::class, 'waits_on_id');
    }

    /**
     * Transition reads (until the old columns are dropped): the mirror in
     * booted() only runs on save, so an unsaved or freshly filled model can hold
     * a value on just one side. Prefer the new column, fall back to the old one.
     */
    public function hardDeadline(): ?Carbon
    {
        return $this->hard_deadline ?? $this->deadline;
    }

    public function softDeadline(): ?Carbon
    {
        return $this->soft_deadline ?? $this->due_date;
    }

    /** G1: a missing size counts as Short. Use `size` itself to tell "not sized yet" apart. */
    public function effectiveSize(): Size
    {
        return $this->size ?? Size::Short;
    }

    /**
     * This task as the planning engine sees it (App\Planning). The old
     * `duration_minutes` hint becomes the session estimate a Block needs; for
     * every other size it is ignored (sizes are coarse on purpose, risk F4).
     */
    public function toPlannedTask(int $sessionMinutes = 25): PlannedTask
    {
        return new PlannedTask(
            id: $this->id,
            size: $this->effectiveSize(),
            estimatedSessions: $this->duration_minutes !== null
                ? (int) ceil($this->duration_minutes / $sessionMinutes)
                : null,
            hardDeadline: $this->hardDeadline()?->toDateString(),
            softDeadline: $this->softDeadline()?->toDateString(),
            areaId: $this->area_id,
        );
    }

    /** The next occurrence this task spawned when it was completed (see syncRepeat()). */
    public function repeatSuccessor(): HasOne
    {
        return $this->hasOne(self::class, 'repeated_from_id');
    }

    /** Which day this task is planned for, if any (see App\Services\DayPlanner). */
    public function dayPlan(): HasOne
    {
        return $this->hasOne(TaskDayPlan::class);
    }

    /** The parts of this task when it was split across several days (see App\Services\DayPlanner::saveSplit()), earliest first. */
    public function sessions(): HasMany
    {
        return $this->hasMany(TaskSession::class)->orderBy('planned_date');
    }

    /**
     * Where a split task stands, derived from its parts: how many are done,
     * which one is next, and whether every part is finished (the cue for the
     * "Aufgabe abhaken?" offer). Null for an ordinary, unsplit task. Reads the
     * loaded relation, so list queries eager-load `sessions` once instead of
     * paying a query per card.
     *
     * @return array{total: int, done: int, position: int, next: ?TaskSession, allDone: bool}|null
     */
    public function sessionProgress(): ?array
    {
        $sessions = $this->sessions;

        if ($sessions->isEmpty()) {
            return null;
        }

        $done = $sessions->filter(fn (TaskSession $s) => $s->isDone())->count();
        $next = $sessions->first(fn (TaskSession $s) => ! $s->isDone());

        return [
            'total' => $sessions->count(),
            'done' => $done,
            'position' => min($done + 1, $sessions->count()),
            'next' => $next,
            'allDone' => $next === null,
        ];
    }

    public function isRepeating(): bool
    {
        return $this->repeat_rule !== null && isset(self::REPEAT_RULES[$this->repeat_rule]);
    }

    /**
     * The date this task's next occurrence falls on: the first date the rule
     * produces that is strictly after today. Counted from the task's own date
     * when it has one (finishing Friday's task on Monday still means "next
     * Friday", not "a week from Monday"), from today when it has none — and
     * always skipping forward past today, so a habit neglected for three weeks
     * comes back as one fresh task, not a backlog of overdue ones.
     */
    public function nextOccurrenceDate(User $user): Carbon
    {
        $today = $user->localToday()->startOfDay();
        $base = ($this->effectiveDate() ?? $today)->copy()->startOfDay();

        $step = function (Carbon $date): Carbon {
            $next = $date->copy();

            return match ($this->repeat_rule) {
                'weekdays' => $next->addDay()->isWeekend() ? $next->next(Carbon::MONDAY) : $next,
                'weekly' => $next->addWeek(),
                'monthly' => $next->addMonthNoOverflow(),
                default => $next->addDay(),
            };
        };

        $candidate = $step($base);

        while ($candidate->lessThanOrEqualTo($today)) {
            $candidate = $step($candidate);
        }

        return $candidate;
    }

    /**
     * Completing a repeating task creates its next occurrence as a fresh,
     * unfinished task (same title/list/notes/importance/duration/size/area/
     * first step, shifted dates, never flagged Heute); un-completing it takes
     * that successor away again while it is still untouched. A no-op for every
     * ordinary task. Called from every place a task's completion can flip —
     * a new invariant needs every write site.
     */
    public function syncRepeat(User $user, bool $done): ?self
    {
        if ($done) {
            if (! $this->isRepeating() || $this->repeatSuccessor()->exists()) {
                return null;
            }

            $next = $this->nextOccurrenceDate($user);
            $base = ($this->effectiveDate() ?? $user->localToday())->copy()->startOfDay();
            $shift = (int) abs($base->diffInDays($next));

            return $user->tasks()->create([
                'title' => $this->title,
                'list' => $this->list,
                'size' => $this->size,
                'area_id' => $this->area_id,
                'first_step' => $this->first_step,
                'is_important' => $this->is_important,
                'notes' => $this->notes,
                'duration_minutes' => $this->duration_minutes,
                'repeat_rule' => $this->repeat_rule,
                'repeated_from_id' => $this->id,
                'sort_order' => 0,
                // Both dates keep their distance from each other; a task with
                // neither gets the occurrence date as its soft due date, so
                // "again on Friday" is visible instead of the task just
                // silently reappearing undated.
                'deadline' => $this->deadline?->copy()->addDays($shift),
                'due_date' => $this->due_date?->copy()->addDays($shift)
                    ?? ($this->deadline === null ? $next : null),
            ]);
        }

        // Un-completing: remove the successor only if nobody has worked with
        // it yet — a finished successor is real history and stays.
        $this->repeatSuccessor()->where('is_completed', false)->delete();

        return null;
    }

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_completed', false);
    }

    public function scopeInList(Builder $query, string $list): Builder
    {
        return $query->where('list', $list);
    }

    /**
     * Order within a visible group: important first, then due within the
     * urgency window, then manual order, then oldest. (The Today area is
     * partitioned out for display by the board component, not here.)
     */
    public function scopeBoardOrdered(Builder $query): Builder
    {
        $threshold = self::today()->addDays(self::URGENCY_DAYS)->toDateString();

        return $query
            ->orderByDesc('is_important')
            ->orderByRaw(
                'CASE WHEN COALESCE(deadline, due_date) IS NOT NULL '
                .'AND COALESCE(deadline, due_date) <= ? THEN 0 ELSE 1 END',
                [$threshold]
            )
            ->orderByRaw('COALESCE(deadline, due_date) IS NULL') // dated before undated
            ->orderByRaw('COALESCE(deadline, due_date)')
            ->orderBy('sort_order')
            ->orderBy('created_at');
    }

    // ── Deadline logic (hard deadline wins over soft due date) ────────

    /** The authenticated user's local calendar day, falling back to the server clock. */
    private static function today(): Carbon
    {
        return auth()->user()?->localToday() ?? Carbon::today();
    }

    /** The date that drives urgency/display: hard deadline takes precedence. */
    public function effectiveDate(): ?Carbon
    {
        return $this->hardDeadline() ?? $this->softDeadline();
    }

    /** True when the effective date is today, in the next URGENCY_DAYS, or overdue. */
    public function isUrgent(): bool
    {
        $date = $this->effectiveDate();

        return $date !== null
            && $date->lessThanOrEqualTo(self::today()->addDays(self::URGENCY_DAYS));
    }

    /** True when the effective date is strictly before today. */
    public function isOverdue(): bool
    {
        $date = $this->effectiveDate();

        return ! $this->is_completed
            && $date !== null
            && $date->lessThan(self::today());
    }

    public function isInbox(): bool
    {
        return $this->list === 'inbox';
    }

    /**
     * True whenever a hard `deadline` is set — meaning the Dringend axis of
     * the "Eisenhower" list concept can never be moved by touching
     * `due_date` alone, since effectiveDate() always prefers `deadline` over
     * `due_date` regardless of what the latter holds. Used to reject a
     * quadrant drag across that axis for such a task (see
     * TaskBoard::reorderEisenhower(), partials/board-eisenhower.blade.php) —
     * the point being to never silently rewrite a hard, externally-imposed
     * deadline just because a card was dropped in a different column.
     */
    public function isUrgencyLocked(): bool
    {
        return $this->hardDeadline() !== null;
    }

    /**
     * The `today_date` value to write alongside a new `is_today`, read from
     * this task's CURRENT (not-yet-saved) state — so every one of the several
     * places that set `is_today` can just splice this into their own
     * `update([...])` call instead of hand-rolling the same guard.
     *
     * Stamps `$targetDate` only when actually ENTERING today (is_today was
     * false); leaves an already-true flag's date untouched, so reordering
     * within the Today zone — doesn't silently re-date an old leftover task to
     * "now"; always clears to null when leaving. See ProgressStats for what
     * this date is used for (it's never read by board display logic).
     */
    public function todayDateFor(bool $newIsToday, ?Carbon $targetDate = null): ?string
    {
        if (! $newIsToday) {
            return null;
        }

        return $this->is_today ? $this->today_date?->toDateString() : $targetDate?->toDateString();
    }

    /**
     * Whether this task's "Heute" flag lines up with today's Planer day-plan
     * — the signal the provenance icon on the card face reads (see
     * partials/task-card.blade.php). Derived, not stored: dayPlan +
     * today_date already carry everything needed, so no new column. True
     * whenever both point at the same day, regardless of which write path
     * actually set is_today — a task that's independently flagged today AND
     * planned for today via the Planer is honestly described either way.
     */
    public function isTodayFromPlanner(): bool
    {
        if (! $this->is_today || $this->today_date === null) {
            return false;
        }

        return $this->dayPlan !== null && $this->dayPlan->planned_date->isSameDay($this->today_date);
    }

    /** True when the effective date comes from a hard deadline (not a soft due date). */
    public function effectiveIsHard(): bool
    {
        return $this->deadline !== null;
    }

    /** Short human label for the effective date: heute / morgen / weekday / d.m. / überfällig. */
    public function effectiveDateLabel(): ?string
    {
        $date = $this->effectiveDate();

        if ($date === null) {
            return null;
        }

        $today = self::today();

        if ($date->lessThan($today)) {
            return 'überfällig';
        }

        // Carbon 3 returns a float; cast for exact day-bucket matching.
        $days = (int) $today->diffInDays($date);

        return match (true) {
            $days === 0 => 'heute',
            $days === 1 => 'morgen',
            $days <= 6 => ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][$date->dayOfWeek],
            default => $date->format('d.m.'),
        };
    }

    /**
     * The first few words of the notes, stripped of the markdown syntax the
     * edit sheet's toolbar inserts (bold/italic/underline/bullet/task-list
     * markers) — a plain-text glance shown on the card face. Null when there
     * are no notes.
     */
    public function notesPreview(int $words = self::NOTES_PREVIEW_WORDS): ?string
    {
        $text = trim((string) $this->notes);

        if ($text === '') {
            return null;
        }

        $text = preg_replace('/^- \[[ xX]\] /m', '', $text);
        $text = preg_replace('/^[-*] /m', '', $text);
        $text = str_replace(['**', '++', '*'], '', $text);
        $text = trim(preg_replace('/\s+/', ' ', $text));

        if ($text === '') {
            return null;
        }

        $parts = explode(' ', $text);
        $preview = implode(' ', array_slice($parts, 0, $words));

        return count($parts) > $words ? $preview.'…' : $preview;
    }

    /**
     * Renders notes markdown to safe HTML — shared by every place notes are
     * written (the task edit sheet, the quick-capture panel) so the safety
     * options and the custom ++underline++ extension stay in one place.
     */
    public static function renderNotesMarkdown(string $text): string
    {
        return Markdown::toHtml($text);
    }
}
