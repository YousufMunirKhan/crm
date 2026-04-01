<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        'api_url' => env('WHATSAPP_API_URL', 'https://graph.facebook.com/v22.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN', 'your_verify_token_here'),
        'app_id' => env('WHATSAPP_APP_ID'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v20.0'),
    ],

    'voodoosms' => [
        'uid' => env('VOODOOSMS_UID', env('SMS_API_KEY')),
        'pass' => env('VOODOOSMS_PASS', env('SMS_SECRET_KEY')),
        'sender_name' => env('SMS_SENDER_NAME', 'EPOS'),
        'default_message' => env('SMS_DEFAULT_MESSAGE'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
    ],

];
