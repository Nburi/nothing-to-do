<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'brainstorm',
        'external_url',
        'deadline',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'deadline'   => 'date',
            'sort_order' => 'integer',
        ];
    }

    /** The authenticated user's local calendar day, falling back to the server clock. */
    private static function today(): Carbon
    {
        return auth()->user()?->localToday() ?? Carbon::today();
    }

    public function isOverdue(): bool
    {
        return $this->deadline !== null && $this->deadline->lessThan(self::today());
    }

    public function isUrgent(): bool
    {
        return $this->deadline !== null && $this->deadline->diffInDays(self::today()) <= 4 && ! $this->isOverdue();
    }

    public function deadlineLabel(): string
    {
        if (! $this->deadline) {
            return '';
        }

        $today = self::today();
        $diff = (int) $today->diffInDays($this->deadline, false);

        if ($diff < 0) {
            return 'Überfällig';
        }
        if ($diff === 0) {
            return 'Heute';
        }
        if ($diff === 1) {
            return 'Morgen';
        }
        if ($diff <= 6) {
            return $this->deadline->locale('de_CH')->isoFormat('ddd');
        }

        return $this->deadline->format('d.m.');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** Active (uncompleted) tasks, in board order — the project's working set. */
    public function activeTasks(): HasMany
    {
        return $this->tasks()->active()->boardOrdered();
    }

    /** Detect a human-readable service name from the stored URL. */
    public function externalServiceName(): string
    {
        if (! $this->external_url) {
            return '';
        }

        $host = strtolower(parse_url($this->external_url, PHP_URL_HOST) ?? '');
        $host = ltrim($host, 'www.');

        $known = match(true) {
            str_contains($host, 'atlassian.net') || str_contains($host, 'jira') => 'Jira',
            str_contains($host, 'github.com') => 'GitHub',
            str_contains($host, 'gitlab.com') => 'GitLab',
            str_contains($host, 'linear.app') => 'Linear',
            str_contains($host, 'trello.com') => 'Trello',
            str_contains($host, 'asana.com') => 'Asana',
            str_contains($host, 'notion.so') || str_contains($host, 'notion.com') => 'Notion',
            str_contains($host, 'clickup.com') => 'ClickUp',
            str_contains($host, 'monday.com') => 'Monday.com',
            str_contains($host, 'basecamp.com') => 'Basecamp',
            str_contains($host, 'azure') => 'Azure DevOps',
            default => null,
        };

        if ($known !== null) {
            return $known;
        }

        // Fall back to the second-level domain (e.g. "mycompany" from mycompany.com).
        $parts = explode('.', $host);
        $sld = count($parts) >= 2 ? $parts[count($parts) - 2] : ($parts[0] ?? '');

        return ucfirst($sld);
    }

    // ── Scopes ────────────────────────────────────────────────────────

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('created_at');
    }
}
