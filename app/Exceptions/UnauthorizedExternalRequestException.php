<?php

namespace App\Exceptions;

use Exception;

class UnauthorizedExternalRequestException extends Exception
{
    public function __construct(string $message = 'External request not authorized', int $code = 403, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}