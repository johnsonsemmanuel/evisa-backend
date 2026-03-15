<?php

namespace App\Exceptions;

use Exception;

class WebhookVerificationException extends Exception
{
    /**
     * Create a new webhook verification exception.
     *
     * @param string $provider
     * @param string $reason
     * @return static
     */
    public static function invalidSignature(string $provider, string $reason = 'Invalid signature'): static
    {
        return new static("Webhook signature verification failed for {$provider}: {$reason}");
    }

    /**
     * Create exception for missing signature.
     *
     * @param string $provider
     * @return static
     */
    public static function missingSignature(string $provider): static
    {
        return new static("Webhook signature header missing for {$provider}");
    }

    /**
     * Create exception for missing webhook secret.
     *
     * @param string $provider
     * @return static
     */
    public static function missingSecret(string $provider): static
    {
        return new static("Webhook secret not configured for {$provider}");
    }
}
