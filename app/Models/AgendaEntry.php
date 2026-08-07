<?php

namespace App\Models;

use Database\Factories\AgendaEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class AgendaEntry extends Model
{
    /** @use HasFactory<AgendaEntryFactory> */
    use HasFactory;

    public const TYPES = [
        'homework' => 'Hausaufgabe',
        'exam' => 'Prüfung',
    ];

    protected $fillable = [
        'type',
        'subject',
        'title',
        'notes',
        'date',
        'is_done',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_done' => 'boolean',
        ];
    }

    /** The authenticated user's local calendar day, falling back to the server clock. */
    private static function today(): Carbon
    {
        return auth()->user()?->localToday() ?? Carbon::today();
    }

    public function isOverdue(): bool
    {
        return ! $this->is_done && $this->date->lessThan(self::today());
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

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
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

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('is_done', false);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('date')->orderBy('created_at');
    }
}
