<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Inbound webhook key (X-Webhook-Key header, or ?key= for providers that
    | can only be given a URL)
    |--------------------------------------------------------------------------
    | Guards /api/webhooks/*. Those endpoints write to customers and
    | communications, so an unset key returns 503 rather than accepting
    | anonymous writes.
    */
    'api_key' => env('WEBHOOK_API_KEY', ''),

];
