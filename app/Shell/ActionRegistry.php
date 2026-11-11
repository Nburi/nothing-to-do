<?php

namespace App\Shell;

use InvalidArgumentException;

/**
 * The one list of actions both layouts expose (docs/REFACTOR_PLAN.md § 1, "Parity"; risk C8).
 *
 * Every entry point in a view is an <x-shell.action key="…" layout="…" mode="…">, which refuses
 * a key that is not listed here. ActionParityTest (A11) renders the shell in board and in session
 * mode and checks that every available action has an entry point in Werkbank and in Stapel.
 * Making an action available is one line here; the test then says where entry points are missing.
 */
final class ActionRegistry
{
    public const LAYOUTS = ['werkbank', 'stapel'];

    public const MODES = ['board', 'session'];

    /** key => [refactor step that builds it, available now] */
    private const ACTIONS = [
        'capture' => ['2.3', true],
        'sort' => ['2.3', true],
        'start' => ['2.2', true],
        'swap' => ['2.2', true],
        'battery' => ['2.2', true],
        'browse_later' => ['2.2', true],
        'reset' => ['3.2', true],
        'move_event' => ['4.1', false],
        'week_plan' => ['4.1', false],
        'pause_days' => ['4.1', false],
        'crunch' => ['4.2', false],
        'keep_mine' => ['3.4', false],
    ];

    /** @return list<Action> */
    public static function all(): array
    {
        $out = [];

        foreach (self::ACTIONS as $key => [$since, $available]) {
            $out[] = new Action($key, $since, $available);
        }

        return $out;
    }

    /** @return list<Action> */
    public static function available(): array
    {
        return array_values(array_filter(self::all(), fn (Action $a) => $a->available));
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::ACTIONS);
    }

    public static function get(string $key): Action
    {
        if (! self::has($key)) {
            throw new InvalidArgumentException("Unknown shell action [{$key}]. Register it in App\\Shell\\ActionRegistry.");
        }

        [$since, $available] = self::ACTIONS[$key];

        return new Action($key, $since, $available);
    }
}
