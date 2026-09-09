<?php

namespace App\Support;

use App\Mail\ServerErrorAlert;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Emails somebody when the server actually breaks.
 *
 * The log has been the only record of a fault, and nobody reads a log until
 * they already know something is wrong - which means the first report of an
 * outage has always come from whoever it stopped working for.
 *
 * Three things this deliberately does not do.
 *
 * **It does not mail the ordinary.** A 404, a failed login, a rejected form
 * are the application working. Mail on those and the alerts become noise
 * within a day, and noise gets filtered, and then the real one is filtered
 * too. Only unhandled faults and 5xx go out.
 *
 * **It does not queue.** There is no queue worker on the server and no cron
 * to start one, so a queued alert would sit in the jobs table forever. It
 * sends inline and accepts the cost, which is why the throttle matters.
 *
 * **It never throws.** This runs inside exception reporting. An alert that
 * fails must leave a line in the log and nothing else - if it raised, it
 * would replace the fault being reported with its own.
 */
class ErrorAlerts
{
    public static function report(Throwable $e): void
    {
        try {
            $recipients = config('alerts.error_email', []);

            if ($recipients === []) {
                return;
            }

            if (! self::worthMailing($e)) {
                return;
            }

            if (! self::passesThrottle($e)) {
                return;
            }

            Mail::to($recipients)->send(new ServerErrorAlert($e, self::context()));
        } catch (Throwable $alertFailure) {
            // The fault itself is already logged by the normal reporting path.
            // This line is about the alert, so it is findable when somebody asks
            // why no email arrived.
            Log::warning('Could not send the server error alert: '.$alertFailure->getMessage());
        }
    }

    /**
     * Faults only. Everything here is the application behaving correctly.
     */
    private static function worthMailing(Throwable $e): bool
    {
        $expected = [
            ValidationException::class,
            AuthenticationException::class,
            AuthorizationException::class,
            ModelNotFoundException::class,
            ThrottleRequestsException::class,
            TokenMismatchException::class,
        ];

        foreach ($expected as $class) {
            if ($e instanceof $class) {
                return false;
            }
        }

        // A 404 or 403 is not a breakage. A deliberate abort(500) is.
        if ($e instanceof HttpExceptionInterface) {
            return $e->getStatusCode() >= 500;
        }

        return true;
    }

    /**
     * One mail per distinct fault per window, under an hourly ceiling.
     */
    private static function passesThrottle(Throwable $e): bool
    {
        $minutes = max(1, (int) config('alerts.throttle_minutes', 15));
        $ceiling = max(1, (int) config('alerts.max_per_hour', 20));

        $signature = sha1($e::class.'|'.$e->getFile().'|'.$e->getLine());

        // add() only succeeds when the key is absent, so the first occurrence in
        // the window wins and the rest are silent.
        if (! Cache::add("error-alert:seen:{$signature}", true, now()->addMinutes($minutes))) {
            return false;
        }

        $hourKey = 'error-alert:count:'.now()->format('YmdH');
        $sent = (int) Cache::get($hourKey, 0);

        if ($sent >= $ceiling) {
            return false;
        }

        Cache::put($hourKey, $sent + 1, now()->addHour());

        return true;
    }

    /**
     * Enough to find the fault without carrying anything private.
     *
     * The request body is left out on purpose: it holds passwords on the login
     * route and bank details on the customer forms, and an alert mailbox is not
     * where either belongs.
     *
     * @return array<string, string>
     */
    private static function context(): array
    {
        if (app()->runningInConsole()) {
            return [
                'Source' => 'Console: '.implode(' ', array_slice((array) ($_SERVER['argv'] ?? []), 1)),
                'When' => now()->toDayDateTimeString(),
            ];
        }

        $request = request();

        return array_filter([
            'URL' => $request?->fullUrl(),
            'Method' => $request?->method(),
            'User' => auth()->check()
                ? auth()->user()->email.' (#'.auth()->id().')'
                : 'Not signed in',
            'IP' => $request?->ip(),
            'When' => now()->toDayDateTimeString(),
        ]);
    }
}
