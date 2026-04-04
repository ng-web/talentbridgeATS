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

    'wipay' => [
        'base_url' => env('WIPAY_BASE_URL', 'https://jm.wipayfinancial.com'),
        'account_number' => env('WIPAY_ACCOUNT_NUMBER'),
        'api_key' => env('WIPAY_API_KEY'),
        'country_code' => env('WIPAY_COUNTRY_CODE', 'JM'),
        'currency' => env('WIPAY_CURRENCY', 'JMD'),
        'environment' => env('WIPAY_ENVIRONMENT', 'live'),
        'fee_structure' => env('WIPAY_FEE_STRUCTURE', 'customer_pay'),
        'origin' => env('WIPAY_ORIGIN', 'KairoxExchange'),
    ],

];
