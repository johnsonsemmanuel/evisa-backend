<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sumsub Integration Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for Sumsub identity verification service integration.
    |
    */

    'enabled' => env('SUMSUB_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | API Credentials
    |--------------------------------------------------------------------------
    |
    | Your Sumsub API credentials. Get these from your Sumsub dashboard.
    |
    */

    'app_token' => env('SUMSUB_APP_TOKEN'),
    'secret_key' => env('SUMSUB_SECRET_KEY'),
    'base_url' => env('SUMSUB_BASE_URL', 'https://api.sumsub.com'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Webhook secret for verifying incoming webhook requests from Sumsub.
    |
    */

    'webhook_secret' => env('SUMSUB_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Verification Requirements
    |--------------------------------------------------------------------------
    |
    | Configure which application types and tiers require Sumsub verification.
    |
    */

    'required_tiers' => env('SUMSUB_REQUIRED_TIERS', 'priority,express'),
    'eta_enabled' => env('SUMSUB_ETA_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Verification Levels
    |--------------------------------------------------------------------------
    |
    | Sumsub verification levels for different application types.
    |
    */

    'levels' => [
        'basic' => 'basic-kyc-level',
        'evisa' => 'evisa-level',
        'eta' => 'eta-level',
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeout Settings
    |--------------------------------------------------------------------------
    |
    | API request timeout settings.
    |
    */

    'timeout' => env('SUMSUB_TIMEOUT', 30),
    'connect_timeout' => env('SUMSUB_CONNECT_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Retry Settings
    |--------------------------------------------------------------------------
    |
    | Retry configuration for failed API requests.
    |
    */

    'max_retries' => env('SUMSUB_MAX_RETRIES', 3),
    'retry_delay' => env('SUMSUB_RETRY_DELAY', 1000), // milliseconds

];