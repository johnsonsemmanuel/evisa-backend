<?php

namespace App\Exceptions;

use Exception;

class FeeNotFoundException extends Exception
{
    /**
     * Create a new exception for missing fee configuration.
     */
    public static function forApplication(
        int $applicationId,
        int $visaTypeId,
        string $processingTier,
        string $nationalityCategory
    ): self {
        return new self(
            "No active fee found for application #{$applicationId}: " .
            "visa_type_id={$visaTypeId}, tier={$processingTier}, nationality={$nationalityCategory}. " .
            "Please configure fees in the admin panel."
        );
    }

    /**
     * Create exception for multiple matching fees (configuration error).
     */
    public static function multipleFeesFound(
        int $applicationId,
        int $count
    ): self {
        return new self(
            "Configuration error: {$count} active fees found for application #{$applicationId}. " .
            "Only one fee should be active for a given combination. " .
            "Please review fee configuration in admin panel."
        );
    }

    /**
     * Create exception for missing visa type.
     */
    public static function visaTypeNotFound(int $visaTypeId): self
    {
        return new self(
            "Visa type #{$visaTypeId} not found. Cannot calculate fee."
        );
    }
}
