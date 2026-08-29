<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
            'staff' => \App\Http\Middleware\EnsureStaffUser::class,
            'portal.customer' => \App\Http\Middleware\EnsurePortalCustomer::class,
            'nav.section' => \App\Http\Middleware\EnsureNavSectionAllowed::class,
            'pos.key' => \App\Http\Middleware\AuthenticatePosApiKey::class,
            'pos.support.key' => \App\Http\Middleware\AuthenticatePosSupportApiKey::class,
            'webhook.key' => \App\Http\Middleware\AuthenticateWebhookKey::class,
        ]);
    })
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        /**
         * API responses are JSON, and never leak internals in production.
         * Everything unexpected is reported and returned as a generic 500 so
         * stack traces and SQL cannot reach a client.
         */
        $isApi = fn (\Illuminate\Http\Request $request) => $request->is('api/*') || $request->expectsJson();

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            // For web routes, redirect to login page (Vue router will handle it)
            return redirect('/login');
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, \Illuminate\Http\Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json(['message' => 'This action is unauthorized.'], 403);
            }
        });

        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, \Illuminate\Http\Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json(['message' => 'Not found.'], 404);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, \Illuminate\Http\Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json(['message' => 'Not found.'], 404);
            }
        });

        $exceptions->render(function (\Illuminate\Routing\Exceptions\BackedEnumCaseNotFoundException|\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e, \Illuminate\Http\Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json(['message' => 'Method not allowed.'], 405);
            }
        });

        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, \Illuminate\Http\Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json([
                    'message' => 'Too many attempts. Please try again shortly.',
                ], 429);
            }
        });

        // Catch-all: report, then return a generic error. Debug builds keep detail.
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            if ($e instanceof \Illuminate\Validation\ValidationException
                || $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                return null; // Laravel already renders these correctly.
            }

            report($e);

            if (config('app.debug')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'exception' => $e::class,
                    'file' => $e->getFile().':'.$e->getLine(),
                ], 500);
            }

            return response()->json([
                'message' => 'Something went wrong. The error has been logged.',
            ], 500);
        });
    })->create();
