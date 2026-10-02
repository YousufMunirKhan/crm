<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the cold calling sweep runner endpoints (/api/cold-calling-runner/*).
 *
 * The runner is a script on an office PC, not a person with a login, and what
 * it posts becomes cold calling contacts. It sends the shared key as an
 * X-Api-Key header.
 */
class AuthenticateSweepRunnerKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('cold_calling.sweep.runner_key', '');

        if ($configured === '') {
            return response()->json([
                'error' => 'The sweep runner is not configured. Set COLD_CALLING_SWEEP_KEY in the environment.',
            ], 503);
        }

        $provided = $request->header('X-Api-Key') ?? $request->bearerToken();

        if (! is_string($provided) || ! hash_equals($configured, $provided)) {
            return response()->json([
                'error' => 'Invalid or missing API key. Use header X-Api-Key.',
            ], 401);
        }

        return $next($request);
    }
}
