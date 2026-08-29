<?php

return [

    /*
    |--------------------------------------------------------------------------
    | POS integration API key (static; sent as X-Api-Key)
    |--------------------------------------------------------------------------
    | Guards /api/pos/*. Endpoints under this prefix create and update
    | customers, tickets and invoices, so they must never be anonymous.
    | No default: an unset key makes the endpoints return 503 rather than
    | silently accepting anonymous writes.
    */
    'api_key' => env('POS_API_KEY', ''),

];
