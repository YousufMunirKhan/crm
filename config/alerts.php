<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Where server errors are emailed
    |--------------------------------------------------------------------------
    |
    | Comma separated. Empty means nobody is told, which is the default so a
    | fresh install does not try to mail a stranger.
    |
    */

    'error_email' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ERROR_ALERT_EMAIL', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | How often the same error may mail
    |--------------------------------------------------------------------------
    |
    | One broken page throws the same exception on every request. Without a
    | gate that is thousands of identical emails in an hour, which gets the
    | sending account suspended and buries the one error nobody has seen.
    |
    | Throttling is per error - same class, file and line - so a second,
    | different fault is never hidden behind the first one's silence.
    |
    */

    'throttle_minutes' => (int) env('ERROR_ALERT_THROTTLE_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Ceiling across all errors
    |--------------------------------------------------------------------------
    |
    | The per-error gate does nothing against a fault that throws a different
    | exception each time. This is the backstop; anything past it is still
    | logged, just not mailed.
    |
    */

    'max_per_hour' => (int) env('ERROR_ALERT_MAX_PER_HOUR', 20),

];
