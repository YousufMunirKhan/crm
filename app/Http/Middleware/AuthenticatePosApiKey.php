<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the POS integration endpoints (/api/pos/*).
 *
 * These endpoints write directly to customers, tickets and invoices, so they
 * must never be reachable anonymously. The desktop POS sends the shared key as
 * an X-Api-Key header.
 */
class AuthenticatePosApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('pos.api_key', '');

        if ($configured === '') {
            return response()->json([
                'error' => 'POS API is not configured. Set POS_API_KEY in the environment.',
            ], 503);
        }

        $provided = $request->header('X-Api-Key')
            ?? $request->header('X-API-Key')
            ?? $request->bearerToken()
            ?? $request->input('api_key');

        if (! is_string($provided) || ! hash_equals($configured, $provided)) {
            return response()->json([
                'error' => 'Invalid or missing API key. Use header X-Api-Key.',
            ], 401);
        }

        return $next($request);
    }
}
