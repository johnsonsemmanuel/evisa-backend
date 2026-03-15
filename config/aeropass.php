<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Aeropass API (Interpol Nominal Check)
    |--------------------------------------------------------------------------
    |
    | Configuration for Aeropass integration for Interpol nominal checks and
    | e-Visa verification requests. See Aeropass ICD documentation for URLs
    | and credentials.
    |
    */

    'enabled' => (bool) env('AEROPASS_ENABLED', false),

    'base_url' => rtrim((string) env('AEROPASS_BASE_URL', 'https://api.aeropass.example.com'), '/'),
    'username' => env('AEROPASS_USERNAME'),
    'password' => env('AEROPASS_PASSWORD'),

    /*
    | Incoming requests from Aeropass (callback / webhook authentication)
    */
    'api_username' => env('AEROPASS_API_USERNAME'),
    'api_password' => env('AEROPASS_API_PASSWORD'),

    'callback_url' => env('AEROPASS_CALLBACK_URL'),

    /*
    | HMAC secret for callback signature verification (Aeropass ICD).
    | Header name configurable; e.g. X-Aeropass-Signature = HMAC-SHA256(body, secret).
    */
    'callback_webhook_secret' => env('AEROPASS_CALLBACK_WEBHOOK_SECRET'),
    'callback_signature_header' => env('AEROPASS_CALLBACK_SIGNATURE_HEADER', 'X-Aeropass-Signature'),

    'timeout_seconds' => (int) env('AEROPASS_TIMEOUT_SECONDS', env('AEROPASS_TIMEOUT', 30)),
    'timeout' => (int) env('AEROPASS_TIMEOUT_SECONDS', env('AEROPASS_TIMEOUT', 30)),
    'retry_delay' => (int) env('AEROPASS_RETRY_DELAY', 2),
    'max_retries' => (int) env('AEROPASS_MAX_RETRIES', 3),

];
