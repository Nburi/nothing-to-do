<?php

namespace App\Models;

use App\Support\Markdown\UnderlineExtension;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /** The board lists. Tasks in the 'projects' list live inside a Project. */
    public const LISTS = ['inbox', 'todos', 'tasks', 'projects'];

    /** Lists offered as quick-add / drag targets on the main board. */
    public const BOARD_LISTS = ['inbox', 'todos', 'tasks'];

    /** Lists that support a Today focus area. Inbox & projects deliberately do not. */
    public const TODAY_LISTS = ['todos', 'tasks'];

    /** Urgency window in days for the soft "due soon" sort bucket. */
    public const URGENCY_DAYS = 4;

    protected $fillable = [
        'title',
        'list',
        'project_id',
        'group_id',
        'emergency_list',
        'is_today',
        'is_important',
        'deadline',
        'due_date',
        'notes',
        'is_completed',
        'completed_at',
        'sort_order',
    ];

    /** Words shown in the card-face notes preview before truncating with an ellipsis. */
    public const NOTES_PREVIEW_WORDS = 8;

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

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TaskGroup::class, 'group_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /** Tasks that live on the main board (Inbox/To-Dos/Tasks), not inside a project. */
    public function scopeOnBoard(Builder $query): Builder
    {
        return $query->whereNull('project_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_completed', false);
    }

    public function scopeInList(Builder $query, string $list): Builder
    {
        return $query->where('list', $list);
    }

    /** Tasks bundled into one task group. */
    public function scopeInGroup(Builder $query, int $groupId): Builder
    {
        return $query->where('group_id', $groupId);
    }

    /** Tasks that stand on their own — not bundled into any group. */
    public function scopeUngrouped(Builder $query): Builder
    {
        return $query->whereNull('group_id');
    }

    /** The board column (inbox/todos/tasks) a project task surfaces under during emergency mode. */
    public function scopeInEmergencyList(Builder $query, string $list): Builder
    {
        return $query->where('emergency_list', $list);
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

    /**
     * Order inside a task group: due-soon first, then dated before undated,
     * then the manual order. Deliberately identical to boardOrdered() *minus*
     * the leading `is_important` sort — inside a group the star is a marker
     * only and must not pull a task to the top (an explicit product decision).
     */
    public function scopeGroupOrdered(Builder $query): Builder
    {
        $threshold = self::today()->addDays(self::URGENCY_DAYS)->toDateString();

        return $query
            ->orderByRaw(
                'CASE WHEN COALESCE(deadline, due_date) IS NOT NULL '
                .'AND COALESCE(deadline, due_date) <= ? THEN 0 ELSE 1 END',
                [$threshold]
            )
            ->orderByRaw('COALESCE(deadline, due_date) IS NULL')
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
        return $this->deadline ?? $this->due_date;
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

    public function isInProject(): bool
    {
        return $this->project_id !== null;
    }

    public function isInGroup(): bool
    {
        return $this->group_id !== null;
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
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        return Str::markdown($text, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ], [new UnderlineExtension()]);
    }
}
