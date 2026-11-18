<?php

namespace App\Models;

use App\Services\Projects\NoteTags;
use App\Support\Markdown\Markdown;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Step 4.3b: a note card of a project (optionally in a stage; none = "Lose") or of a group.
 * The body is Markdown (Niels, 2026-11-18), rendered by the app's one safe renderer; #tags in it
 * are its tags. Writes go through App\Services\Projects\NoteEditor.
 */
class Note extends Model
{
    protected $fillable = ['body', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TaskGroup::class, 'group_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ProjectStage::class, 'stage_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('name');
    }

    /** Safe HTML of the body, with each #tag wrapped so it can be styled and clicked. */
    public function html(): string
    {
        return NoteTags::mark(Markdown::toHtml($this->body));
    }
}
