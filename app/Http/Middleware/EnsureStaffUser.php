<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff-only boundary for the main API.
 *
 * Customers authenticate with Sanctum tokens too (customer portal login), and
 * the portal routes are nested inside the same auth:sanctum group. Without
 * this, a customer token satisfies the guard on every staff endpoint.
 */
class EnsureStaffUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // A token issued before somebody was deactivated must stop working
        // too, not just their next login attempt.
        //
        // Only when the flag is actually loaded and false. The column defaults
        // to true, so a model that never selected it is active - blocking on a
        // missing attribute would reject people for a column nobody read.
        $attributes = $user->getAttributes();

        if (array_key_exists('is_active', $attributes) && ! $user->is_active) {
            return response()->json(['message' => 'This account is no longer active.'], 403);
        }

        if (! $user instanceof User) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        return $next($request);
    }
}
