<?php

namespace App\Models;

use Database\Factories\GroupNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One note card inside a task group's Notizen column — deliberately several
 * small, separate cards rather than one growing document (see the
 * group_notes migration for why).
 */
class GroupNote extends Model
{
    /** @use HasFactory<GroupNoteFactory> */
    use HasFactory;

    protected $fillable = [
        'content',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TaskGroup::class, 'task_group_id');
    }

    /** Rendered through TaskGroup::renderNotes() so the safety options stay in one place. */
    public function contentHtml(): string
    {
        return TaskGroup::renderNotes((string) $this->content);
    }
}
