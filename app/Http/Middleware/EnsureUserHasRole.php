<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level role gate: `->middleware('role:Admin,Manager')`.
 *
 * Replaces inline string comparisons inside controllers, and closes the
 * controllers that previously had no role check at all.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        foreach ($roles as $role) {
            if ($user->isRole($role)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'This action is unauthorized.'], 403);
    }
}
