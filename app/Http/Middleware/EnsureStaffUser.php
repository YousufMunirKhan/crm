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

        if (! $user instanceof User) {
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        return $next($request);
    }
}
