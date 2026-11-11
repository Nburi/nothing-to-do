<?php

namespace App\Models;

use App\Planning\Battery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One user's evening (local date): the battery check-in and the picks skipped
 * with "Another". Read and written through App\Services\Suggestions.
 */
class Evening extends Model
{
    protected $fillable = ['date', 'battery', 'skipped_task_ids'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'skipped_task_ids' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** G4: not asked or skipped counts as Okay. */
    public function batteryLevel(): Battery
    {
        return Battery::fromNullable($this->battery);
    }

    /** @return list<int> */
    public function skippedTaskIds(): array
    {
        return array_values(array_map('intval', $this->skipped_task_ids ?? []));
    }
}
