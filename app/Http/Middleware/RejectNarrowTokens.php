<?php

namespace App\Http\Middleware;

use App\Support\NarrowAbility;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps single-purpose phone tokens (NarrowAbility) away from the general API.
 * Every token created before this existed carries '*' or MCP abilities and is
 * untouched; only a token whose abilities are all narrow is refused here.
 */
class RejectNarrowTokens
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && NarrowAbility::onlyNarrow($token->abilities ?? [])) {
            return response()->json(['message' => 'Dieses Token ist nur für einen Kurzbefehl gedacht.'], 403);
        }

        return $next($request);
    }
}
