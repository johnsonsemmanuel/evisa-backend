<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class GCBApiException extends RuntimeException
{
    public function __construct(
        string $message = 'GCB API error',
        int $code = 0,
        ?Throwable $previous = null,
        public ?int $statusCode = null,
        public ?string $errorCode = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
