<?php

return [

    /*
    |--------------------------------------------------------------------------
    | POS Desktop — Support messages API key (static; send as X-Api-Key)
    |--------------------------------------------------------------------------
    | No committed default: the previous hardcoded key was in git history and
    | must be treated as compromised. Set POS_SUPPORT_API_KEY in .env.
    */
    'api_key' => env('POS_SUPPORT_API_KEY', ''),

];
