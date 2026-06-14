<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Task extends Model
{
    /** @use HasFactory<\Database\Factories\TaskFactory> */
    use HasFactory;

    /** The three lists. A future 'projects' value slots in here. */
    public const LISTS = ['inbox', 'todos', 'tasks'];

    /** Lists that support a Today focus area. Inbox deliberately does not. */
    public const TODAY_LISTS = ['todos', 'tasks'];

    /** Urgency window in days for the soft "due soon" sort bucket. */
    public const URGENCY_DAYS = 4;

    protected $fillable = [
        'title',
        'list',
        'is_today',
        'is_important',
        'deadline',
        'due_date',
        'is_completed',
        'completed_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_today' => 'boolean',
            'is_important' => 'boolean',
            'is_completed' => 'boolean',
            'deadline' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        $threshold = Carbon::today()->addDays(self::URGENCY_DAYS)->toDateString();

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

    /** The date that drives urgency/display: hard deadline takes precedence. */
    public function effectiveDate(): ?Carbon
    {
        return $this->deadline ?? $this->due_date;
    }

    /** True when the effective date is today, in the next URGENCY_DAYS, or overdue. */
    public function isUrgent(): bool
    {
        $date = $this->effectiveDate();

        return $date !== null
            && $date->lessThanOrEqualTo(Carbon::today()->addDays(self::URGENCY_DAYS));
    }

    /** True when the effective date is strictly before today. */
    public function isOverdue(): bool
    {
        $date = $this->effectiveDate();

        return ! $this->is_completed
            && $date !== null
            && $date->lessThan(Carbon::today());
    }

    public function isInbox(): bool
    {
        return $this->list === 'inbox';
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

        $today = Carbon::today();

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
}
