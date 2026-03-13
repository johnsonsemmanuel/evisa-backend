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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway Services
    |--------------------------------------------------------------------------
    */

    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'merchant_email' => env('PAYSTACK_MERCHANT_EMAIL'),
    ],

    'gcb' => [
        'base_url' => env('GCB_BASE_URL', 'https://epayuat.gcbltd.com:98/paymentgateway'),
        'api_key' => env('GCB_API_KEY'),
        'allowed_ips' => array_filter(explode(',', env('GCB_ALLOWED_IPS', ''))),
    ],

    'stripe' => [
        'public_key' => env('STRIPE_PUBLIC_KEY'),
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Aeropass API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Aeropass system integration for Interpol checks
    | and E-Visa verification requests.
    |
    */

    'aeropass' => [
        'base_url' => env('AEROPASS_BASE_URL', 'https://api.aeropass.example.com'),
        'username' => env('AEROPASS_USERNAME'),
        'password' => env('AEROPASS_PASSWORD'),
        'api_username' => env('AEROPASS_API_USERNAME'), // For incoming requests from Aeropass
        'api_password' => env('AEROPASS_API_PASSWORD'), // For incoming requests from Aeropass
        'timeout' => env('AEROPASS_TIMEOUT', 20),
        'retry_delay' => env('AEROPASS_RETRY_DELAY', 2),
        'max_retries' => env('AEROPASS_MAX_RETRIES', 3),
        'enabled' => env('AEROPASS_ENABLED', false),
    ],

];
