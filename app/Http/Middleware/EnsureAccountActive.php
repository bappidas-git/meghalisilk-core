<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deactivated users/admins are rejected on every authenticated request, not
 * only at login. Their tokens are revoked and the client drops the session (401).
 */
class EnsureAccountActive
{
    public const MESSAGE = 'This account has been deactivated. Please contact support if you think this is a mistake.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && isset($user->is_active) && ! $user->is_active) {
            $user->tokens()->delete();

            return response()->json(['message' => self::MESSAGE], 401);
        }

        return $next($request);
    }
}
