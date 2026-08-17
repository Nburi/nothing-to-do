<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name', 'email', 'password', 'task_reset_time',
    'pomodoro_work', 'pomodoro_short_break', 'pomodoro_long_break', 'pomodoro_long_every', 'pomodoro_autostart',
    'notify_event_start', 'notify_pomo_start', 'notify_break_start', 'notify_event_upcoming',
    'timezone_offset', 'timezone_auto_dst',
    'prepare_time_of_day', 'prepare_reminder_mode', 'prepare_reminder_time',
    'prepared_on', 'prepare_reminder_sent_on', 'prepare_prompt_dismissed_on',
    'emergency_project_id',
    'last_seen_at', 'show_presence',
    'deadline_preview_enabled', 'deadline_preview_days',
    'homework_preview_enabled',
    'daily_task_goal',
    'notify_daily_reminder', 'daily_reminder_time', 'daily_reminder_sent_on',
    'notify_streak_risk', 'streak_risk_sent_on',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Mirrors the DB-level default so a *freshly created* user already has it in
     * memory. Without this, `show_presence` is null until the row is reloaded
     * (the fresh-model gotcha in §10), and null is falsy — so `touchPresence()`
     * would silently skip the first heartbeat after registration and
     * `toggleShowPresence()` would compute `! null === true` and turn the
     * setting *on* when the user asked to turn it off.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'show_presence' => true,
        'deadline_preview_enabled' => true,
        'homework_preview_enabled' => true,
        'daily_task_goal' => 5,
        'notify_daily_reminder' => false,
        'daily_reminder_time' => '19:00',
        'notify_streak_risk' => false,
    ];

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return HasMany<ScheduleEvent, $this> */
    public function scheduleEvents(): HasMany
    {
        return $this->hasMany(ScheduleEvent::class);
    }

    /** @return HasMany<EventTemplate, $this> */
    public function eventTemplates(): HasMany
    {
        return $this->hasMany(EventTemplate::class);
    }

    /** @return HasMany<EventCategory, $this> */
    public function eventCategories(): HasMany
    {
        return $this->hasMany(EventCategory::class);
    }

    /** @return HasMany<PushSubscription, $this> */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }

    /** @return HasMany<AgendaEntry, $this> */
    public function agendaEntries(): HasMany
    {
        return $this->hasMany(AgendaEntry::class);
    }

    /** @return HasMany<CraftIdea, $this> */
    public function craftIdeas(): HasMany
    {
        return $this->hasMany(CraftIdea::class);
    }

    /** Shared class/group agendas this user belongs to (a class, a study group, …). */
    public function agendaSpaces(): BelongsToMany
    {
        return $this->belongsToMany(AgendaSpace::class)->withTimestamps();
    }

    /** @return HasMany<AgendaSpace, $this> */
    public function ownedAgendaSpaces(): HasMany
    {
        return $this->hasMany(AgendaSpace::class, 'owner_id');
    }

    // ── Presence ──────────────────────────────────────────────────────

    /**
     * How long a heartbeat keeps someone "online". The client beats once a
     * minute while the tab is in the foreground, so this deliberately tolerates
     * one missed beat plus latency rather than flickering people offline
     * between ticks.
     */
    public const PRESENCE_TTL_SECONDS = 150;

    /**
     * Record that this user's app is open and in the foreground right now.
     *
     * Written with a raw query-builder update on purpose: this runs once a
     * minute per open tab, and it must not fire model events or bump
     * `updated_at` — "was recently looking at the app" is not a change to the
     * account.
     *
     * Opting out doesn't just hide the timestamp, it stops recording it. Anyone
     * who turns presence off is asking not to be tracked, not merely not to be
     * displayed.
     */
    public function touchPresence(): void
    {
        if (! $this->show_presence) {
            return;
        }

        $now = Carbon::now();

        static::query()->whereKey($this->getKey())->toBase()->update(['last_seen_at' => $now]);

        $this->last_seen_at = $now;
    }

    /**
     * Is the app open and in the foreground for this person right now?
     *
     * Compares raw UTC against raw UTC deliberately — this is elapsed time, not
     * a wall-clock reading, so the user's `timezone_offset` must not enter into
     * it (same reasoning as the Pomodoro countdown, see §7).
     */
    public function isOnline(): bool
    {
        return $this->show_presence
            && $this->last_seen_at !== null
            && $this->last_seen_at->greaterThan(Carbon::now()->subSeconds(self::PRESENCE_TTL_SECONDS));
    }

    /**
     * "gerade eben" / "vor 5 Min" / "vor 3 Std" / "vor 2 Tagen", or null when
     * there is nothing to show. Hand-rolled rather than `diffForHumans()` for
     * the same reason `Task::effectiveDateLabel()` is: the app's locale is not
     * German, and these labels are part of the UI copy.
     */
    public function lastSeenLabel(): ?string
    {
        if (! $this->show_presence || $this->last_seen_at === null) {
            return null;
        }

        // Carbon 3 returns a float here; cast so the buckets below match.
        $minutes = (int) $this->last_seen_at->diffInMinutes(Carbon::now());

        return match (true) {
            $minutes < 1 => 'gerade eben',
            $minutes < 60 => "vor {$minutes} Min",
            $minutes < 2880 => 'vor '.intdiv($minutes, 60).' Std',
            default => 'vor '.intdiv($minutes, 1440).' Tagen',
        };
    }

    /** The project currently in "emergency mode", or null if not active. */
    public function emergencyProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'emergency_project_id');
    }

    public function isInEmergencyMode(): bool
    {
        return $this->emergency_project_id !== null;
    }

    /** The Pomodoro rhythm (minutes / count) driving each category's focus timer. */
    public function pomodoro(): array
    {
        return [
            'work' => (int) ($this->pomodoro_work ?? 25),
            'short_break' => (int) ($this->pomodoro_short_break ?? 5),
            'long_break' => (int) ($this->pomodoro_long_break ?? 15),
            'long_every' => (int) ($this->pomodoro_long_every ?? 4),
        ];
    }

    /**
     * The start of the current "visibility window" for completed tasks.
     * Completed tasks are shown until the daily reset time (default 01:00).
     * Returns the most recent past occurrence of that time.
     */
    public function completedWindowStart(): Carbon
    {
        $time = $this->task_reset_time ?? '01:00';
        [$h, $m] = array_pad(explode(':', $time), 2, '00');

        $now = $this->localNow();

        $resetToday = $now->copy()->startOfDay()
            ->setHour((int) $h)
            ->setMinute((int) $m)
            ->setSecond(0);

        return $now->greaterThanOrEqualTo($resetToday)
            ? $resetToday
            : $resetToday->subDay();
    }

    /**
     * Base UTC offset in hours (standard/winter time, no DST applied), including
     * fractional quarter/half-hour offsets (e.g. +5.5 for India, +5.75 for Nepal).
     * Entered directly by the user (e.g. +1 for Switzerland, -5 for New York)
     * since this is a single-user app, not a full IANA timezone picker.
     */
    public function timezoneOffsetHours(): float
    {
        return (float) ($this->timezone_offset ?? 0);
    }

    /**
     * The effective UTC offset (in minutes) right now: the base offset, plus
     * +1 hour when European DST is active and auto-correction is enabled.
     * DST transition dates are borrowed from PHP's own "Europe/Zurich" tz
     * database rather than hand-rolled EU rules — same rule, reusing data
     * PHP already ships.
     */
    public function utcOffsetMinutes(?Carbon $at = null): int
    {
        $offset = $this->timezoneOffsetHours();

        if ($this->timezone_auto_dst) {
            $at ??= Carbon::now();
            $dt = new \DateTime('@'.$at->getTimestamp());
            $dt->setTimezone(new \DateTimeZone('Europe/Zurich'));
            $offset += (int) $dt->format('I');
        }

        return (int) round($offset * 60);
    }

    /** The current moment, shifted to this user's configured local wall time. */
    public function localNow(): Carbon
    {
        return Carbon::now()->addMinutes($this->utcOffsetMinutes());
    }

    /** Midnight of the user's current local calendar day. */
    public function localToday(): Carbon
    {
        return $this->localNow()->startOfDay();
    }

    /**
     * The day the "Vorbereitung" ritual (`PrepareTomorrow`) plans for:
     * "morning" mode plans the already-running today, "evening" (default)
     * plans tomorrow.
     */
    public function prepareTargetDate(): Carbon
    {
        return $this->prepare_time_of_day === 'morning'
            ? $this->localToday()
            : $this->localToday()->addDay();
    }

    /** True once `PrepareTomorrow::finish()` has stamped today (the user's local day) as done. */
    public function hasPreparedToday(): bool
    {
        return $this->prepared_on?->toDateString() === $this->localToday()->toDateString();
    }

    /**
     * True during the half of the day an "automatic" reminder is relevant:
     * before noon for "morning" mode, from noon on for "evening" mode. Drives
     * the in-app banner on the board — it's deliberately a loose half-day
     * window, not a specific time, since "automatic" means "whenever you
     * happen to open the app", unlike "fixed" mode's exact time.
     */
    public function isWithinPrepareWindow(): bool
    {
        $hour = $this->localNow()->hour;

        return $this->prepare_time_of_day === 'morning' ? $hour < 12 : $hour >= 12;
    }

    /**
     * HH:MM the daily push reminder should fire, or null if reminders are
     * off. "fixed" mode uses the user's own chosen time; "automatic" mode has
     * no user-chosen time (it's driven by opening the app instead) so this is
     * only its fallback — a single "last call" push if the app was never
     * opened during the relevant half of the day.
     */
    public function prepareReminderDueTime(): ?string
    {
        return match ($this->prepare_reminder_mode) {
            'fixed' => $this->prepare_reminder_time,
            'automatic' => $this->prepare_time_of_day === 'morning' ? '10:00' : '21:00',
            default => null,
        };
    }

    /**
     * How many tasks completed in one local calendar day counts as "hit the daily
     * goal" — drives the progress ring and the "goal reached" celebration (see
     * ProgressStats::celebrationFor()).
     */
    public function dailyTaskGoal(): int
    {
        return (int) ($this->daily_task_goal ?? 5);
    }

    /**
     * Fixed "last call" time for the streak-at-risk push — deliberately not
     * user-configurable (mirrors prepareReminderDueTime()'s 'automatic' fallback):
     * one plain warning shortly before midnight, not another field to tune.
     */
    public const STREAK_RISK_DUE_TIME = '21:00';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'timezone_offset' => 'float',
            'timezone_auto_dst' => 'boolean',
            'pomodoro_autostart' => 'boolean',
            'notify_event_start' => 'boolean',
            'notify_pomo_start' => 'boolean',
            'notify_break_start' => 'boolean',
            'notify_event_upcoming' => 'boolean',
            'prepared_on' => 'date',
            'prepare_reminder_sent_on' => 'date',
            'prepare_prompt_dismissed_on' => 'date',
            'last_seen_at' => 'datetime',
            'show_presence' => 'boolean',
            'deadline_preview_enabled' => 'boolean',
            'deadline_preview_days' => 'integer',
            'homework_preview_enabled' => 'boolean',
            'daily_task_goal' => 'integer',
            'notify_daily_reminder' => 'boolean',
            'daily_reminder_sent_on' => 'date',
            'notify_streak_risk' => 'boolean',
            'streak_risk_sent_on' => 'date',
        ];
    }
}
