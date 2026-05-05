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

];
