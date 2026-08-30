<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user-defined custom field on an EventCategory (e.g. "Trainingstyp",
 * "Dauer") — fully customizable: the user names it, picks a type, and (for
 * 'select') defines its own options. A ScheduleEvent occurrence carries the
 * actual value via schedule_event_attribute_values (ScheduleEvent::attributeValues()),
 * never the category or its template — see CLAUDE.md, "Kategorie-Attribute".
 */
class CategoryAttribute extends Model
{
    public const TYPES = [
        'text' => 'Text',
        'number' => 'Zahl',
        'select' => 'Auswahl',
        'checkbox' => 'Ja/Nein',
    ];

    /** Topografie colour tokens a 'select' option may carry, mirroring ScheduleEvent::EVENT_COLORS. */
    public const OPTION_COLORS = ['contour', 'overprint', 'forest', 'signal', 'ink'];

    protected $fillable = [
        'event_category_id',
        'name',
        'type',
        'options',
        'unit',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'event_category_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** @return array<int, array{label: string, color: string}> */
    public function optionsList(): array
    {
        return $this->type === 'select' ? ($this->options ?? []) : [];
    }

    /** The Topografie colour token for a given select value, or null (unknown value, or not a 'select' attribute). */
    public function colorForValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        foreach ($this->optionsList() as $option) {
            if (($option['label'] ?? null) === $value) {
                return $option['color'] ?? null;
            }
        }

        return null;
    }

    /**
     * Normalizes a raw form value into what gets stored (or null to store
     * nothing at all — an attribute left blank leaves no row, never an empty
     * one, same convention as every other optional field in this app).
     */
    public function normalizeValue(mixed $raw): ?string
    {
        return match ($this->type) {
            'number' => is_numeric($raw) ? (string) $raw : null,
            'select' => in_array($raw, array_column($this->optionsList(), 'label'), true) ? $raw : null,
            'checkbox' => $raw ? '1' : null,
            default => is_string($raw) && trim($raw) !== '' ? trim($raw) : null,
        };
    }
}
