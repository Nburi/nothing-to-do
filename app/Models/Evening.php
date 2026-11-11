<?php

namespace App\Models;

use App\Planning\Battery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * One user's evening (local date): the battery check-in, the picks skipped with
 * "Another" (App\Services\Planning\Suggestions) and the ten minute reset
 * (App\Services\Planning\EveningReset, step 3.2).
 */
class Evening extends Model
{
    protected $fillable = ['date', 'battery', 'skipped_task_ids', 'reset_started_at', 'reset_closed_at', 'battery_asked_at'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'skipped_task_ids' => 'array',
            'reset_started_at' => 'immutable_datetime',
            'reset_closed_at' => 'immutable_datetime',
            'battery_asked_at' => 'immutable_datetime',
        ];
    }

    /** The row for a user's local date, or null when nothing was written yet. */
    public static function for(User $user, string $date): ?self
    {
        return self::query()->where('user_id', $user->id)->whereDate('date', $date)->first();
    }

    /** The row for a user's local date, created on the first write. */
    public static function forWrite(User $user, string $date): self
    {
        $evening = self::for($user, $date);
        if ($evening !== null) {
            return $evening;
        }

        // Two devices can arrive here at once (risk C14): the unique index decides, the loser reads the winner's row.
        try {
            return $user->evenings()->create(['date' => $date]);
        } catch (UniqueConstraintViolationException) {
            return self::for($user, $date);
        }
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

    /** G4: asked once per evening. A battery set through the chip counts as answered. */
    public function batteryAsked(): bool
    {
        return $this->battery_asked_at !== null || $this->battery !== null;
    }
}
