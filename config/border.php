<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Boarding Authorization Code (BAC) Expiry
    |--------------------------------------------------------------------------
    |
    | The number of hours a Boarding Authorization Code remains valid after
    | generation. Default is 24 hours as per specification.
    |
    */

    'bac_expiry_hours' => env('BAC_EXPIRY_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limit for border verification API endpoints (requests per minute).
    |
    */

    'rate_limit' => env('BORDER_VERIFICATION_RATE_LIMIT', 60),

    /*
    |--------------------------------------------------------------------------
    | Audit Log Retention
    |--------------------------------------------------------------------------
    |
    | Number of years to retain audit logs for compliance.
    |
    */

    'audit_retention_years' => env('AUDIT_LOG_RETENTION_YEARS', 7),

];
