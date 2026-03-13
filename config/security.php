<?php

/**
 * Government-Grade Security Configuration
 * Centralized security settings for the Ghana eVisa Platform.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Token Expiration
    |--------------------------------------------------------------------------
    | JWT/Sanctum token expiration settings.
    */
    'token' => [
        'expiration_minutes' => env('TOKEN_EXPIRATION_MINUTES', 60),
        'refresh_expiration_days' => env('REFRESH_TOKEN_EXPIRATION_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Policy
    |--------------------------------------------------------------------------
    | Government-grade password requirements.
    */
    'password' => [
        'min_length' => 12,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_symbols' => true,
        'prevent_reuse' => 5, // Number of previous passwords to check
        'max_age_days' => 90, // Force password change after 90 days
    ],

    /*
    |--------------------------------------------------------------------------
    | Brute Force Protection
    |--------------------------------------------------------------------------
    | Progressive lockout thresholds.
    */
    'brute_force' => [
        'max_attempts' => 5,
        'lockout_minutes' => [
            5 => 1,      // 5 attempts = 1 minute
            10 => 5,     // 10 attempts = 5 minutes
            15 => 15,    // 15 attempts = 15 minutes
            20 => 60,    // 20 attempts = 1 hour
            30 => 1440,  // 30 attempts = 24 hours
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    | API rate limiting configuration.
    */
    'rate_limiting' => [
        'api' => [
            'requests_per_minute' => 60,
            'requests_per_hour' => 1000,
        ],
        'login' => [
            'attempts_per_minute' => 5,
            'attempts_per_hour' => 20,
        ],
        'upload' => [
            'uploads_per_hour' => 30,
            'max_file_size_mb' => 10,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Upload Security
    |--------------------------------------------------------------------------
    | Allowed file types and validation rules.
    */
    'uploads' => [
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'application/pdf',
        ],
        'allowed_extensions' => [
            'jpg',
            'jpeg',
            'png',
            'pdf',
        ],
        'max_file_size' => 10485760, // 10MB
        'max_image_dimension' => 10000, // pixels
        'scan_for_malware' => env('SCAN_UPLOADS_FOR_MALWARE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption
    |--------------------------------------------------------------------------
    | Data encryption settings.
    */
    'encryption' => [
        'algorithm' => 'AES-256-GCM',
        'key_rotation_days' => 365,
        'pii_fields' => [
            'first_name',
            'last_name',
            'date_of_birth',
            'passport_number',
            'nationality',
            'email',
            'phone',
            'address',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Security
    |--------------------------------------------------------------------------
    | Session management settings.
    */
    'session' => [
        'idle_timeout_minutes' => 30,
        'absolute_timeout_hours' => 8,
        'single_session' => false, // Allow multiple sessions
        'bind_to_ip' => false, // Bind session to IP (can cause issues with mobile)
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    | What actions to log.
    */
    'audit' => [
        'log_all_requests' => false,
        'log_mutations' => true, // POST, PUT, PATCH, DELETE
        'log_authentication' => true,
        'log_document_access' => true,
        'log_status_changes' => true,
        'retention_days' => 365,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    | HTTP security headers.
    */
    'headers' => [
        'hsts_max_age' => 31536000,
        'hsts_include_subdomains' => true,
        'hsts_preload' => true,
        'frame_options' => 'DENY',
        'content_type_options' => 'nosniff',
        'xss_protection' => '1; mode=block',
    ],

    /*
    |--------------------------------------------------------------------------
    | IP Restrictions
    |--------------------------------------------------------------------------
    | Admin panel and sensitive endpoint restrictions.
    */
    'ip_restrictions' => [
        'admin_whitelist' => env('ADMIN_IP_WHITELIST', ''),
        'api_blacklist' => env('API_IP_BLACKLIST', ''),
        'enable_geo_blocking' => env('ENABLE_GEO_BLOCKING', false),
        'blocked_countries' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Monitoring
    |--------------------------------------------------------------------------
    | Anomaly detection thresholds.
    */
    'monitoring' => [
        'failed_logins_threshold' => 10,
        'api_requests_threshold' => 100,
        'document_downloads_threshold' => 50,
        'alert_email' => env('SECURITY_ALERT_EMAIL', ''),
        'alert_sms' => env('SECURITY_ALERT_SMS', ''),
    ],

];
