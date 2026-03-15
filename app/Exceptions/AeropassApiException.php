<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class AeropassApiException extends RuntimeException
{
    public function __construct(
        string $message = 'Aeropass API error',
        int $code = 0,
        ?Throwable $previous = null,
        public ?string $responseBody = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function fromResponse(int $status, string $body): self
    {
        return new self(
            "Aeropass API error: HTTP {$status}. " . (strlen($body) > 200 ? substr($body, 0, 200) . '...' : $body),
            $status,
            null,
            $body,
        );
    }
}
