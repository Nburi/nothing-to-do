<?php

namespace App\Planning;

/**
 * Task size in sessions (G1). Measured in work minutes so several quick tasks
 * can share one 25 minute session.
 *
 * Long is counted at its upper bound (3 sessions): an optimistic forecast that
 * hides a crunch is worse than one that warns a little early (risk F4).
 */
enum Size: string
{
    case Quick = 'quick';
    case Short = 'short';
    case Long = 'long';
    case Block = 'block';

    /** A missing size counts as Short (G1). */
    public static function fromNullable(?string $value): self
    {
        return $value === null ? self::Short : (self::tryFrom($value) ?? self::Short);
    }

    public function workMinutes(?int $estimatedSessions = null, int $sessionMinutes = 25): int
    {
        return match ($this) {
            self::Quick => 10,
            self::Short => $sessionMinutes,
            self::Long => 3 * $sessionMinutes,
            self::Block => max(4, $estimatedSessions ?? 4) * $sessionMinutes,
        };
    }
}
