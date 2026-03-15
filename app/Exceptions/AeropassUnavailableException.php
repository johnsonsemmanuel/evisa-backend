<?php

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Aeropass returned 5xx (server error) or network failure. Job will retry.
 */
class AeropassUnavailableException extends Exception
{
    public function __construct(
        string $message = 'Aeropass service unavailable (5xx or timeout)',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
