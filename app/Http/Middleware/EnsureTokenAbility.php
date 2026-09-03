<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Scope check for Sanctum tokens.
 *
 * A token presented on the wrong side (customer token on /admin/*, admin token
 * on customer routes) is answered with 401 rather than 403: the frontend only
 * drops a stale session on 401 (guide §8.2 / §42.8).
 */
class EnsureTokenAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        $expectedModel = $ability === 'admin' ? Admin::class : User::class;

        if (! $user instanceof $expectedModel || ! $token || ! $user->tokenCan($ability)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
