<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Asynchronous sending
    |--------------------------------------------------------------------------
    | When true, outbound messages are dispatched to the queue instead of being
    | sent inside the HTTP request. Requires a running `queue:work`.
    |
    | Read through config (not env()) so it survives `php artisan config:cache`
    | - an env() call outside a config file returns null once the config is
    | cached, silently forcing synchronous sends.
    */
    'queue_async' => (bool) env('COMMUNICATION_QUEUE_ASYNC', false),

    /*
    |--------------------------------------------------------------------------
    | Bulk send chunking
    |--------------------------------------------------------------------------
    | Recipients per queued chunk for bulk campaigns.
    */
    'bulk_chunk_size' => (int) env('COMMUNICATION_BULK_CHUNK', 100),

];
