<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records that a user has opened a given module's page at least once —
 * stamped by App\Http\Middleware\RecordModuleVisit on every matching page
 * load. One row per (user, module); `updated_at` is the last visit,
 * `created_at` the first. Read by App\Models\FeatureAnnouncement to power
 * "only for people who actually use this page" audience scoping.
 */
class ModuleVisit extends Model
{
    protected $fillable = [
        'user_id',
        'module_key',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
