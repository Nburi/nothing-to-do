<?php

namespace App\Support;

/**
 * Sanctum abilities for single-purpose tokens that live on a phone (iPhone
 * Shortcuts). A token that carries only narrow abilities can do exactly that
 * one thing and nothing else in the API (see RejectNarrowTokens), so a token
 * copied out of a Shortcut cannot read or change a user's tasks (risk C10).
 */
final class NarrowAbility
{
    /** Report "I'm home" and receive the evening nudge (refactor spike 0.1, R2). */
    public const ARRIVAL = 'arrival:notify';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::ARRIVAL];
    }

    /** True when the token can do nothing but narrow things ('*' is never narrow). */
    public static function onlyNarrow(array $abilities): bool
    {
        return $abilities !== [] && array_diff($abilities, self::all()) === [];
    }
}
