<?php

namespace App\Models;

use App\Services\PomodoroCycle;
use Database\Factories\ScheduleEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A concrete dated block on the timeline — either a free-text Termin
 * (appointment) or a block tied to a user-configured EventCategory. Times are
 * stored as plain "HH:MM" strings; minute math is done on the fly.
 */
class ScheduleEvent extends Model
{
    /** @use HasFactory<ScheduleEventFactory> */
    use HasFactory;

    /** Topografie colour tokens a Termin or category may use. */
    public const EVENT_COLORS = ['contour', 'overprint', 'forest', 'signal', 'ink'];

    protected $fillable = [
        'template_id',
        'category_id',
        'title',
        'color',
        'date',
        'start_time',
        'end_time',
        'is_cancelled',
        'pomodoro_started_at',
        'pomodoro_phase',
        'pomodoro_cycle',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_cancelled' => 'boolean',
            'pomodoro_started_at' => 'datetime',
            'pomodoro_cycle' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EventTemplate::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    /** Visible events only — cancelled recurring occurrences are hidden. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_cancelled', false);
    }

    public function scopeForDay(Builder $query, Carbon|string $date): Builder
    {
        $date = $date instanceof Carbon ? $date->toDateString() : $date;

        return $query->whereDate('date', $date);
    }

    public function scopeForRange(Builder $query, Carbon|string $start, Carbon|string $end): Builder
    {
        $start = $start instanceof Carbon ? $start->toDateString() : $start;
        $end = $end instanceof Carbon ? $end->toDateString() : $end;

        // Compare by date part — robust whether a row stored "Y-m-d" (raw insert)
        // or "Y-m-d 00:00:00" (Eloquent date cast on SQLite).
        return $query->whereDate('date', '>=', $start)->whereDate('date', '<=', $end);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('date')->orderBy('start_time');
    }

    // ── Time helpers ──────────────────────────────────────────────────

    public static function toMinutes(string $hm): int
    {
        [$h, $m] = array_pad(explode(':', $hm), 2, '0');

        return (int) $h * 60 + (int) $m;
    }

    public static function fromMinutes(int $minutes): string
    {
        $minutes = max(0, min(24 * 60, $minutes));

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public function startMinutes(): int
    {
        return self::toMinutes($this->start_time);
    }

    public function endMinutes(): int
    {
        return self::toMinutes($this->end_time);
    }

    public function durationMinutes(): int
    {
        return max(0, $this->endMinutes() - $this->startMinutes());
    }

    public function isAppointment(): bool
    {
        return $this->category_id === null;
    }

    public function isCategory(): bool
    {
        return $this->category_id !== null;
    }

    /** The live category name, falling back to the stored snapshot if it was deleted. */
    public function displayTitle(): string
    {
        return $this->category?->name ?? (string) $this->title;
    }

    /** Resolve the colour token: live category colour, else the stored snapshot. */
    public function colorToken(): string
    {
        return $this->category?->color ?: ($this->color ?: 'contour');
    }

    // ── Position relative to "now" (for the strip / focus timer) ───────

    private function nowMinutes(Carbon $now): int
    {
        return $now->hour * 60 + $now->minute;
    }

    public function isActive(Carbon $now): bool
    {
        return $this->date->isSameDay($now)
            && $this->nowMinutes($now) >= $this->startMinutes()
            && $this->nowMinutes($now) < $this->endMinutes();
    }

    public function isPast(Carbon $now): bool
    {
        if ($this->date->lessThan($now->copy()->startOfDay())) {
            return true;
        }

        return $this->date->isSameDay($now) && $this->nowMinutes($now) >= $this->endMinutes();
    }

    /** Elapsed fraction 0–1 (only meaningful while active). */
    public function progress(Carbon $now): float
    {
        $duration = $this->durationMinutes();

        if ($duration <= 0) {
            return 0.0;
        }

        $elapsed = $this->nowMinutes($now) - $this->startMinutes();

        return max(0.0, min(1.0, $elapsed / $duration));
    }

    /** Seconds remaining until this event ends (for the live countdown). */
    public function secondsRemaining(Carbon $now): int
    {
        $end = $this->date->copy()->startOfDay()->addMinutes($this->endMinutes());

        return max(0, $now->diffInSeconds($end, false));
    }

    /**
     * The current Pomodoro state, or null if the timer has never been started
     * (a tap on "Start" is required — reaching the scheduled time never starts
     * it on its own). Remaining/total are second-precise so the header ring
     * can count down smoothly even though phase boundaries land on whole
     * minutes.
     *
     * Two frozen/running states beyond the obvious "ticking down":
     *  - `awaiting_next` (autostart disabled): the stored phase finished and
     *    `pomodoro_started_at` was cleared to freeze the clock — `next_phase`/
     *    `next_cycle` describe what a manual continue would start.
     *  - self-healing cascade (autostart enabled): if the client's local timer
     *    never fired the transition (backgrounded tab, etc.), this walks
     *    forward through however many phases have fully elapsed since
     *    `pomodoro_started_at`, so a stale read still reflects reality.
     *
     * @param  array{work:int,short_break:int,long_break:int,long_every:int}  $rhythm
     * @return array{phase:string,cycle:int,remaining_seconds:int,total_seconds:int,running:bool,awaiting_next:bool,next_phase:?string,next_cycle:?int}|null
     */
    public function pomodoroPhaseNow(Carbon $now, array $rhythm, bool $autostart): ?array
    {
        if ($this->pomodoro_phase === null) {
            return null;
        }

        $phase = $this->pomodoro_phase;
        $cycle = $this->pomodoro_cycle;
        $durationSeconds = PomodoroCycle::durationMinutes($phase, $rhythm) * 60;

        if ($this->pomodoro_started_at === null) {
            $next = PomodoroCycle::next($phase, $cycle, $rhythm);

            return [
                'phase' => $phase,
                'cycle' => $cycle,
                'remaining_seconds' => 0,
                'total_seconds' => $durationSeconds,
                'running' => false,
                'awaiting_next' => true,
                'next_phase' => $next['phase'],
                'next_cycle' => $next['cycle'],
            ];
        }

        // Carbon 3 returns a float; cast so downstream int arithmetic stays exact.
        $elapsedSeconds = max(0, (int) $this->pomodoro_started_at->diffInSeconds($now, false));

        if ($autostart) {
            while ($elapsedSeconds >= $durationSeconds) {
                $elapsedSeconds -= $durationSeconds;
                $next = PomodoroCycle::next($phase, $cycle, $rhythm);
                $phase = $next['phase'];
                $cycle = $next['cycle'];
                $durationSeconds = PomodoroCycle::durationMinutes($phase, $rhythm) * 60;
            }
        }

        return [
            'phase' => $phase,
            'cycle' => $cycle,
            'remaining_seconds' => max(0, $durationSeconds - $elapsedSeconds),
            'total_seconds' => $durationSeconds,
            'running' => $elapsedSeconds < $durationSeconds,
            'awaiting_next' => false,
            'next_phase' => null,
            'next_cycle' => null,
        ];
    }

    /**
     * Materialise concrete occurrences of recurring templates across a date
     * range. Idempotent: a (template, date) pair that already has any row —
     * including a cancelled tombstone — is skipped, so this never duplicates
     * and never resurrects a deleted occurrence.
     */
    public static function materializeRange(User $user, Carbon $start, Carbon $end): void
    {
        $templates = EventTemplate::forUser($user)->recurring()->get();

        if ($templates->isEmpty()) {
            return;
        }

        // One query for everything already materialised in the window.
        $existing = self::forUser($user)
            ->whereNotNull('template_id')
            ->forRange($start, $end)
            ->get(['template_id', 'date'])
            ->map(fn ($e) => $e->template_id.'|'.$e->date->toDateString())
            ->flip();

        $rows = [];
        $now = now();

        foreach ($templates as $template) {
            for ($date = $start->copy(); $date->lessThanOrEqualTo($end); $date->addDay()) {
                if (! $template->occursOn($date)) {
                    continue;
                }

                if ($existing->has($template->id.'|'.$date->toDateString())) {
                    continue;
                }

                $startTime = $template->default_start ?: '08:00';
                $rows[] = [
                    'user_id' => $user->id,
                    'template_id' => $template->id,
                    'category_id' => $template->category_id,
                    'title' => $template->displayName(),
                    'color' => $template->colorToken(),
                    'date' => $date->toDateString(),
                    'start_time' => $startTime,
                    'end_time' => self::fromMinutes(self::toMinutes($startTime) + $template->duration),
                    'is_cancelled' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            // insertOrIgnore + the (user,template,date) unique index makes this
            // race-safe: a concurrent render that already inserted the same
            // occurrence is silently skipped instead of duplicating it.
            self::insertOrIgnore($rows);
        }
    }
}
