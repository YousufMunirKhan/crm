<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces users.nav_permissions server-side: `->middleware('nav.section:invoices')`.
 *
 * The sidebar whitelist previously only hid menu entries in the SPA, so any
 * user could still call the endpoints behind a hidden section directly.
 */
class EnsureNavSectionAllowed
{
    public function handle(Request $request, Closure $next, string ...$sections): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        foreach ($sections as $section) {
            if ($user->allowsNavSection($section)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'This action is unauthorized.'], 403);
    }
}
