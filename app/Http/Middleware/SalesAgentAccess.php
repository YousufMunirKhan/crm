<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SalesAgentAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Allow admin and manager full access
        if ($user->isRole('Admin') || $user->isRole('Manager')) {
            return $next($request);
        }

        // For sales agents, the filtering is done in the controllers
        // This middleware just ensures they're authenticated
        if ($user->isRole('Sales') || $user->isRole('CallAgent')) {
            return $next($request);
        }

        // Other roles may have different access rules
        return $next($request);
    }
}
