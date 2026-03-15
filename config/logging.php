<?php

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    */
    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    */
    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    */
    'channels' => [

        // Main stack — all environments (use env for config cache compatibility)
        'stack' => [
            'driver' => 'stack',
            'channels' => env('APP_ENV') === 'production'
                ? ['json_daily', 'critical_slack']
                : explode(',', (string) env('LOG_STACK', 'single')),
            'ignore_exceptions' => false,
        ],

        // JSON structured log — production standard
        'json_daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => 30,
            'formatter' => JsonFormatter::class,
            'replace_placeholders' => true,
            'tap' => [App\Logging\PIIMaskingTap::class],
        ],

        // Critical errors → Slack + email immediately
        'critical_slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', 'eVisa Platform Alerts'),
            'emoji' => env('LOG_SLACK_EMOJI', ':rotating_light:'),
            'level' => env('LOG_SLACK_LEVEL', 'error'),
            'replace_placeholders' => true,
        ],

        // Single file — local / non-production
        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
            'tap' => [App\Logging\PIIMaskingTap::class],
        ],

        // Daily (non-JSON) — optional
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
            'tap' => [App\Logging\PIIMaskingTap::class],
        ],

        // Legacy slack channel name (same as critical_slack when used directly)
        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', 'Laravel Log'),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        // Payment-specific channel (separate file, longer retention)
        'payment' => [
            'driver' => 'daily',
            'path' => storage_path('logs/payment.log'),
            'level' => 'info',
            'days' => 90,
            'formatter' => JsonFormatter::class,
            'replace_placeholders' => true,
            'tap' => [App\Logging\PIIMaskingTap::class],
        ],

        // Queue/job channel
        'queue' => [
            'driver' => 'daily',
            'path' => storage_path('logs/queue.log'),
            'level' => 'warning',
            'days' => 14,
            'formatter' => JsonFormatter::class,
            'replace_placeholders' => true,
        ],

        // Sentry (if DSN provided)
        'sentry' => [
            'driver' => 'sentry',
            'level' => 'error',
            'bubble' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

        // Government-Grade Security Audit Log
        'security' => [
            'driver' => 'daily',
            'path' => storage_path('logs/security.log'),
            'level' => 'debug',
            'days' => 365,
            'replace_placeholders' => true,
            'tap' => [App\Logging\PIIMaskingTap::class],
        ],

        // Authentication audit log
        'auth' => [
            'driver' => 'daily',
            'path' => storage_path('logs/auth.log'),
            'level' => 'info',
            'days' => 365,
            'replace_placeholders' => true,
            'tap' => [App\Logging\PIIMaskingTap::class],
        ],

        // Document access audit log
        'document_access' => [
            'driver' => 'daily',
            'path' => storage_path('logs/document_access.log'),
            'level' => 'info',
            'days' => 365,
            'replace_placeholders' => true,
            'tap' => [App\Logging\PIIMaskingTap::class],
        ],

    ],

];
