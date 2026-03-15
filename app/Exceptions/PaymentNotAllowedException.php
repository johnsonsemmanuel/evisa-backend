<?php

namespace App\Exceptions;

use Exception;

class PaymentNotAllowedException extends Exception
{
    /**
     * Create exception for already paid application.
     *
     * @param int $applicationId
     * @return static
     */
    public static function alreadyPaid(int $applicationId): static
    {
        return new static("Application #{$applicationId} already has a successful payment");
    }

    /**
     * Create exception for invalid application status.
     *
     * @param int $applicationId
     * @param string $currentStatus
     * @return static
     */
    public static function invalidStatus(int $applicationId, string $currentStatus): static
    {
        return new static("Application #{$applicationId} is not in a payable state (current status: {$currentStatus})");
    }

    /**
     * Create exception for recent payment initiation (rate limiting).
     *
     * @param int $applicationId
     * @param int $secondsRemaining
     * @return static
     */
    public static function recentInitiation(int $applicationId, int $secondsRemaining): static
    {
        return new static("Application #{$applicationId} has a payment initiated less than 5 minutes ago. Please wait {$secondsRemaining} seconds before retrying.");
    }

    /**
     * Create exception for unauthorized access.
     *
     * @param int $applicationId
     * @return static
     */
    public static function unauthorized(int $applicationId): static
    {
        return new static("You are not authorized to initiate payment for application #{$applicationId}");
    }
}
