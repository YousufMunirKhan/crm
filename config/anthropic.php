<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Anthropic (Claude) API — optional cold-calling website extraction
    |--------------------------------------------------------------------------
    |
    | Set ANTHROPIC_API_KEY in .env (recommended) or save the key in
    | Settings → Cold calling. Never commit API keys to the repository.
    |
    */

    'api_key' => env('ANTHROPIC_API_KEY', ''),

    'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-5'),

    'timeout' => (int) env('ANTHROPIC_TIMEOUT', 60),

    'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 512),

];
