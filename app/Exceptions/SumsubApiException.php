<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class SumsubApiException extends RuntimeException
{
    public function __construct(
        string $message = 'Sumsub API error',
        int $code = 0,
        ?Throwable $previous = null,
        public ?int $statusCode = null,
        public ?string $sumsubErrorCode = null,
        public ?string $applicantId = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function fromResponse(int $statusCode, string $body, ?string $applicantId = null): self
    {
        $data = json_decode($body, true);
        $sumsubCode = $data['code'] ?? $data['errorCode'] ?? null;
        $description = $data['description'] ?? $data['message'] ?? $body;

        return new self(
            "Sumsub API returned {$statusCode}: " . $description,
            $statusCode,
            null,
            $statusCode,
            $sumsubCode,
            $applicantId,
        );
    }
}
