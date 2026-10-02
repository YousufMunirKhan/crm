<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Run discovery synchronously (no queue worker)
    |--------------------------------------------------------------------------
    |
    | When true (default), the job runs in the same HTTP request so status moves
    | past "pending" without `php artisan queue:work`. Turn off for large imports
    | and process with a queue worker instead (set QUEUE_CONNECTION=database and
    | COLD_CALLING_RUN_SYNC=false).
    |
    */

    'run_sync' => filter_var(env('COLD_CALLING_RUN_SYNC', '1'), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Area sweeps (Google Maps scraper on an office PC)
    |--------------------------------------------------------------------------
    |
    | The scraper is a Docker container, and the live host cannot run one. So
    | the CRM only queues the areas; scripts/gmaps-sweep/sweep.php on a PC
    | collects the queue, scrapes, and posts the rows back with this key in
    | X-Api-Key. No default: an unset key closes the runner endpoints (503)
    | rather than accepting anonymous writes.
    |
    */

    'sweep' => [
        'runner_key' => env('COLD_CALLING_SWEEP_KEY', ''),

        // Business types searched in each area. One scraper keyword per type,
        // so every extra type is more requests against Google from one IP.
        'business_types' => [
            'restaurant',
            'takeaway',
            'cafe',
            'bakery',
            'pub',
            'convenience store',
            'off licence',
            'butcher',
        ],

        // How far the scraper scrolls each result list. Higher finds more and
        // is slower and likelier to get the PC's IP rate-limited.
        'depth' => 5,

        // Seconds the scraper may spend on one area before giving up.
        'max_time' => 900,

        // A claimed sweep nobody finished (PC switched off mid-run) goes back
        // on the queue after this many minutes.
        'stale_after_minutes' => 120,
    ],

];
