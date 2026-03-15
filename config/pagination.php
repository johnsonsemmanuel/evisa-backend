<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Pagination Settings
    |--------------------------------------------------------------------------
    |
    | These values control the default pagination behavior across the application.
    | They help prevent memory exhaustion from large datasets.
    |
    */

    'default_per_page' => 20,
    'max_per_page' => 100,

    /*
    |--------------------------------------------------------------------------
    | Endpoint-Specific Limits
    |--------------------------------------------------------------------------
    |
    | Some endpoints may need different limits based on data size and usage patterns.
    |
    */

    'limits' => [
        'admin_reports' => [
            'default' => 50,
            'max' => 500,
        ],
        'analytics' => [
            'default' => 25,
            'max' => 100,
        ],
        'applications' => [
            'default' => 20,
            'max' => 100,
        ],
        'payments' => [
            'default' => 25,
            'max' => 100,
        ],
        'batch_operations' => [
            'default' => 50,
            'max' => 200,
        ],
        'reference_data' => [
            'default' => 50,
            'max' => 200,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Limits
    |--------------------------------------------------------------------------
    |
    | Limits for data export operations to prevent server overload.
    |
    */

    'export' => [
        'max_records' => 10000,
        'queue_threshold' => 1000, // Queue exports above this limit
    ],

    /*
    |--------------------------------------------------------------------------
    | Cursor Pagination
    |--------------------------------------------------------------------------
    |
    | Settings for cursor-based pagination (more efficient for large datasets).
    |
    */

    'cursor' => [
        'default_per_page' => 25,
        'max_per_page' => 100,
    ],
];