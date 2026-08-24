<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's private note on a shared AgendaEntry — never read or written
 * except through AgendaEntry::privateNoteFor()/setPrivateNoteFor(), which
 * always scope by the *authenticated* user. There is deliberately no relation
 * on AgendaEntry that returns every user's private notes at once; the only
 * way to reach this table is by asking for one specific user's row.
 */
class AgendaEntryNote extends Model
{
    protected $fillable = [
        'agenda_entry_id',
        'user_id',
        'notes',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(AgendaEntry::class, 'agenda_entry_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
