<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The two languages of the app (step 5.1) and the user's choice between them.
 *
 * German is the default and the fallback for anything unknown, so a stale or damaged value in
 * `users.locale` never produces a page without words. Every key lives in lang/de and lang/en
 * (LangParityTest).
 */
final class Locales
{
    /** @var list<string> */
    public const SUPPORTED = ['de', 'en'];

    public const DEFAULT = 'de';

    public static function supports(?string $locale): bool
    {
        return $locale !== null && in_array($locale, self::SUPPORTED, true);
    }

    public static function for(?User $user): string
    {
        return $user !== null && self::supports($user->locale) ? $user->locale : self::DEFAULT;
    }

    /** Makes translations, dates and validation messages follow the locale for the rest of the request. */
    public static function apply(string $locale): void
    {
        app()->setLocale($locale);
        Carbon::setLocale($locale);
    }

    /**
     * Runs a callback in the user's language, for things said outside a request (push messages
     * from the scheduler). The previous locale is restored afterwards.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function within(?User $user, callable $callback): mixed
    {
        $previous = app()->getLocale();
        $previousCarbon = Carbon::getLocale();
        self::apply(self::for($user));

        try {
            return $callback();
        } finally {
            app()->setLocale($previous);
            Carbon::setLocale($previousCarbon);
        }
    }
}
