<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the generic inbound webhooks (/api/webhooks/*).
 *
 * These write directly to customers and communications, so they must not be
 * anonymous. Providers that support custom headers send the shared key as
 * X-Webhook-Key; providers that only support a URL can pass ?key=.
 */
class AuthenticateWebhookKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('webhooks.api_key', '');

        if ($configured === '') {
            return response()->json([
                'error' => 'Webhook endpoint is not configured. Set WEBHOOK_API_KEY in the environment.',
            ], 503);
        }

        $provided = $request->header('X-Webhook-Key')
            ?? $request->bearerToken()
            ?? $request->query('key');

        if (! is_string($provided) || ! hash_equals($configured, $provided)) {
            return response()->json(['error' => 'Invalid or missing webhook key.'], 401);
        }

        return $next($request);
    }
}
