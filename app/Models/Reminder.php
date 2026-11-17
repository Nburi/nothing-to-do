<?php

namespace App\Models;

use Database\Factories\ReminderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A dated "don't forget" note — see the reminders migration. Not a Task on
 * purpose: no list, no estimate, no streak. All rules about what may be
 * written and when pushes go out live in App\Services\Reminders.
 */
class Reminder extends Model
{
    /** @use HasFactory<ReminderFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'remind_on',
        'notify_evening_before',
        'notify_on_day',
        'acknowledged_at',
        'notified_before_at',
        'notified_on_at',
    ];

    protected function casts(): array
    {
        return [
            // Explicit :Y-m-d, same reason as TaskDayPlan (CLAUDE.md, the today_date trap).
            'remind_on' => 'date:Y-m-d',
            'notify_evening_before' => 'boolean',
            'notify_on_day' => 'boolean',
            'acknowledged_at' => 'datetime',
            'notified_before_at' => 'datetime',
            'notified_on_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('acknowledged_at');
    }

    public function scopeOn(Builder $query, Carbon|string $date): Builder
    {
        return $query->whereDate('remind_on', $date instanceof Carbon ? $date->toDateString() : $date);
    }

    public function scopeFromDate(Builder $query, Carbon|string $date): Builder
    {
        return $query->whereDate('remind_on', '>=', $date instanceof Carbon ? $date->toDateString() : $date);
    }

    public function scopeChronological(Builder $query): Builder
    {
        return $query->orderBy('remind_on')->orderBy('id');
    }

    /** heute / morgen / Mi 29.10. — hand-rolled like every other date label in the app (never diffForHumans()). */
    public function dateLabel(Carbon $today): string
    {
        $days = (int) round($today->diffInDays($this->remind_on->copy()->startOfDay(), false));

        return match (true) {
            $days === 0 => 'heute',
            $days === 1 => 'morgen',
            default => ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'][$this->remind_on->dayOfWeek].' '.$this->remind_on->isoFormat('D.M.'),
        };
    }
}
