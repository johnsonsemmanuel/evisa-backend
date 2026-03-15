<?php

use Sentry\Event;
use Sentry\EventHint;

return [

    'dsn' => env('SENTRY_LARAVEL_DSN'),

    'environment' => env('APP_ENV', 'production'),

    'release' => env('APP_VERSION', '1.0.0'),

    'traces_sample_rate' => env('APP_ENV') === 'production' ? 0.1 : 1.0,

    'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0.1),

    /*
    | Strip PII from Sentry events before sending.
    | Request data is sanitized so sensitive keys are not sent to Sentry.
    */
    'before_send' => function (Event $event, EventHint $hint): ?Event {
        $request = $event->getRequest();
        if ($request !== null && method_exists($request, 'getData')) {
            $data = $request->getData();
            if (is_array($data)) {
                $piiKeys = ['passport_number', 'date_of_birth', 'password', 'password_confirmation', 'token', 'email_encrypted', 'phone_encrypted'];
                foreach ($piiKeys as $key) {
                    if (array_key_exists($key, $data)) {
                        $data[$key] = '[REDACTED]';
                    }
                }
                if (method_exists($request, 'setData')) {
                    $request->setData($data);
                }
            }
        }
        return $event;
    },

];
