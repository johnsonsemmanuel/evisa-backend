<?php

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Aeropass returned 4xx (client error). Job will retry.
 */
class AeropassSubmissionException extends Exception
{
    public function __construct(
        string $message = 'Aeropass submission rejected (4xx)',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
