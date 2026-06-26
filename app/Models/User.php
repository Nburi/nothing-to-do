<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

#[Fillable([
    'name', 'email', 'password', 'task_reset_time',
    'brief_when', 'brief_time', 'brief_dismissed_on',
    'pomodoro_work', 'pomodoro_short_break', 'pomodoro_long_break', 'pomodoro_long_every',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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

    /** The Pomodoro rhythm (minutes / count) used to generate Work-Sessions. */
    public function pomodoro(): array
    {
        return [
            'work' => (int) ($this->pomodoro_work ?? 25),
            'short_break' => (int) ($this->pomodoro_short_break ?? 5),
            'long_break' => (int) ($this->pomodoro_long_break ?? 15),
            'long_every' => (int) ($this->pomodoro_long_every ?? 4),
        ];
    }

    /** The day the Brief currently plans for: tomorrow in the evening, today in the morning. */
    public function briefTargetDate(): Carbon
    {
        return ($this->brief_when ?? 'evening') === 'morning'
            ? Carbon::today()
            : Carbon::tomorrow();
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

        $resetToday = Carbon::today()
            ->setHour((int) $h)
            ->setMinute((int) $m)
            ->setSecond(0);

        return now()->greaterThanOrEqualTo($resetToday)
            ? $resetToday
            : $resetToday->subDay();
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
            'brief_dismissed_on' => 'date',
        ];
    }
}
