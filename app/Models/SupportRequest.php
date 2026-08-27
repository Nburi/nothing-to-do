<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A feedback note or support request — one model with a `type` column,
 * mirroring AgendaEntry's homework/exam split. Submitted by any account
 * (App\Livewire\SupportCenter), triaged by admins only
 * (App\Livewire\Admin\SupportQueue). The submitter always sees their own
 * status, but never anyone else's. See CLAUDE.md, "Hilfe-Center & Support".
 */
class SupportRequest extends Model
{
    /**
     * @var array<string, array{label: string}>
     */
    public const TYPES = [
        'feedback' => ['label' => 'Feedback'],
        'support' => ['label' => 'Support-Anfrage'],
    ];

    public const DEFAULT_TYPE = 'feedback';

    /**
     * Topografie tones: `open` is deliberately neutral (the same "nothing to
     * signal yet" look as a draft badge), `in_progress` reuses contour (this
     * app's "something time-bound/active" tone), `resolved` reuses forest
     * (positive outcome), `closed` is the quietest of all — archived, not a
     * result either way. None of these route through signal: this app
     * reserves that tone for danger/urgency, and a support request sitting
     * open is not an emergency.
     *
     * @var array<string, array{label: string, tone: string}>
     */
    public const STATUSES = [
        'open' => ['label' => 'Offen', 'tone' => 'ink'],
        'in_progress' => ['label' => 'In Bearbeitung', 'tone' => 'contour'],
        'resolved' => ['label' => 'Erledigt', 'tone' => 'forest'],
        'closed' => ['label' => 'Geschlossen', 'tone' => 'faint'],
    ];

    public const DEFAULT_STATUS = 'open';

    protected $fillable = [
        'user_id',
        'type',
        'subject',
        'message',
        'status',
        'response',
        'responded_by',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    public function typeMeta(): array
    {
        return self::TYPES[$this->type] ?? self::TYPES[self::DEFAULT_TYPE];
    }

    public function typeLabel(): string
    {
        return $this->typeMeta()['label'];
    }

    public function statusMeta(): array
    {
        return self::STATUSES[$this->status] ?? self::STATUSES[self::DEFAULT_STATUS];
    }

    public function statusLabel(): string
    {
        return $this->statusMeta()['label'];
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeOfStatus(Builder $query, ?string $status): Builder
    {
        return $status !== null ? $query->where('status', $status) : $query;
    }

    public function scopeNewestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }
}
