<?php

namespace App\Planning;

/**
 * The evening battery check-in (CONCEPT core loop, G4): one tap, low / okay /
 * good. It sizes the suggestion; it never changes the button order (CONVENTIONS 4).
 * Skipping the question counts as Okay.
 */
enum Battery: string
{
    case Low = 'low';
    case Okay = 'okay';
    case Good = 'good';

    public static function fromNullable(?string $value): self
    {
        return $value === null ? self::Okay : (self::tryFrom($value) ?? self::Okay);
    }
}
