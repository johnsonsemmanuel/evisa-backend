<?php

namespace App\Enums;

enum PaymentGateway: string
{
    case GCB = 'gcb';
    case Paystack = 'paystack';

    public function label(): string
    {
        return match($this) {
            self::GCB => 'GCB Bank',
            self::Paystack => 'Paystack',
        };
    }

    public function isActive(): bool
    {
        return match($this) {
            self::GCB => config('services.gcb.enabled', true),
            self::Paystack => config('services.paystack.enabled', true),
        };
    }

    public function supportsCurrency(string $currency): bool
    {
        return match($this) {
            self::GCB => in_array($currency, ['GHS', 'USD']),
            self::Paystack => in_array($currency, ['GHS', 'USD', 'NGN']),
        };
    }

    public function getWebhookUrl(): string
    {
        return match($this) {
            self::GCB => route('webhooks.gcb'),
            self::Paystack => route('webhooks.paystack'),
        };
    }
}