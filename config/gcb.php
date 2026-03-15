<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GCB Payment Gateway
    |--------------------------------------------------------------------------
    |
    | Configuration for Ghana Commercial Bank (GCB) payment gateway integration.
    | Used for eVisa fee collection. Obtain credentials from GCB integration team.
    |
    */

    'merchant_id' => env('GCB_MERCHANT_ID'),
    'secret_key' => env('GCB_SECRET_KEY', env('GCB_API_KEY')),
    'base_url' => env('GCB_BASE_URL'),
    'webhook_secret' => env('GCB_WEBHOOK_SECRET'),
    'environment' => env('GCB_ENVIRONMENT', 'production'),
    'timeout' => (int) env('GCB_TIMEOUT', 30),
    'currency' => env('GCB_CURRENCY', 'GHS'),

    /*
    | Optional: bank account display / settlement (for receipts, reporting)
    */
    'allowed_ips' => array_filter(array_map('trim', explode(',', (string) env('GCB_ALLOWED_IPS', '')))),
    'bank_account_number' => env('GCB_BANK_ACCOUNT_NUMBER'),
    'bank_name' => env('GCB_BANK_NAME', 'Ghana Commercial Bank'),
    'account_name' => env('GCB_ACCOUNT_NAME', 'Ghana Immigration Service - eVisa'),
    'branch' => env('GCB_BRANCH', 'Accra Main Branch'),
    'swift_code' => env('GCB_SWIFT_CODE', 'GHCBGHAC'),

];
