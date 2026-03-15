<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when required data for Aeropass is missing (e.g. required dates).
 * Do not send null dates to Aeropass — validate first.
 */
class AeropassValidationException extends RuntimeException
{
    public function __construct(
        string $message = 'Aeropass validation failed: required field missing',
        int $code = 0,
        ?Throwable $previous = null,
        public ?string $field = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
