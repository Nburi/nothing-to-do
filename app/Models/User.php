<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name', 'email', 'password', 'task_reset_time',
    'pomodoro_work', 'pomodoro_short_break', 'pomodoro_long_break', 'pomodoro_long_every', 'pomodoro_autostart',
    'notify_event_start', 'notify_pomo_start', 'notify_break_start',
    'timezone_offset', 'timezone_auto_dst',
    'prepare_time_of_day', 'prepare_reminder_mode', 'prepare_reminder_time',
    'prepared_on', 'prepare_reminder_sent_on', 'prepare_prompt_dismissed_on',
    'emergency_project_id',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
     * Get the attributes that should be cast.
     *
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
            'prepared_on' => 'date',
            'prepare_reminder_sent_on' => 'date',
            'prepare_prompt_dismissed_on' => 'date',
        ];
    }
}
