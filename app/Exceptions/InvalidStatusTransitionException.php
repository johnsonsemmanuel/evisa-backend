<?php

namespace App\Exceptions;

use Exception;

class InvalidStatusTransitionException extends Exception
{
    public function __construct(string $message = "Invalid status transition", int $code = 422, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function render()
    {
        return response()->json([
            'error' => 'Invalid Status Transition',
            'message' => $this->getMessage(),
        ], $this->getCode());
    }
}