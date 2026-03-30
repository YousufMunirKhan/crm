<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePosSupportApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('pos-support.api_key', '');
        if ($configured === '') {
            return response()->json(['error' => 'POS support API is not configured.'], 503);
        }

        $provided = $request->header('X-Api-Key')
            ?? $request->header('X-API-Key')
            ?? $request->bearerToken()
            ?? $request->input('api_key');

        if (!is_string($provided) || !hash_equals($configured, $provided)) {
            return response()->json(['error' => 'Invalid or missing API key. Use header X-Api-Key.'], 401);
        }

        return $next($request);
    }
}
