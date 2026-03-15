<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paystack Payment Gateway
    |--------------------------------------------------------------------------
    |
    | Paystack is used as a fallback payment gateway for eVisa fees (GCB primary).
    | Public key is safe for frontend; secret key must never be exposed.
    |
    */

    'public_key'   => env('PAYSTACK_PUBLIC_KEY'),
    'secret_key'   => env('PAYSTACK_SECRET_KEY'),
    'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),
    'base_url'     => rtrim((string) env('PAYSTACK_BASE_URL', 'https://api.paystack.co'), '/'),
    'currency'     => env('PAYSTACK_CURRENCY', 'GHS'),

    'merchant_email' => env('PAYSTACK_MERCHANT_EMAIL'),
    'timeout' => (int) env('PAYSTACK_TIMEOUT', 30),

];
