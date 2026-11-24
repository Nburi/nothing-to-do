<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Step 4.3b: a tag, typed as #name in a note. Tags are per user and shared across all projects
 * and groups (Niels, 2026-11-17). Names are lower case; unused tags are removed (NoteEditor).
 */
class Tag extends Model
{
    protected $fillable = ['name'];

    public function notes(): BelongsToMany
    {
        return $this->belongsToMany(Note::class);
    }
}
