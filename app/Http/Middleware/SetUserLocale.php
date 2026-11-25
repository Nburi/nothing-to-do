<?php

namespace App\Http\Middleware;

use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Step 5.1: signed-in users get the language they chose (users.locale). Guests and everything
 * else stay on the app default, German. Runs on every web request, Livewire updates included
 * (they go through the web group too), so a re-rendered component never flips back to German.
 */
class SetUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            Locales::apply(Locales::for($user));
        }

        return $next($request);
    }
}
