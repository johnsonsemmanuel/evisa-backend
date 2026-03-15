<?php

namespace App\Exceptions;

use Exception;

/**
 * InvalidFileContentException
 * 
 * Thrown when file content validation fails (magic numbers, size, etc.)
 * 
 * @package App\Exceptions
 */
class InvalidFileContentException extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(string $message = 'Invalid file content', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Render the exception as an HTTP response.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'invalid_file_content',
        ], $this->code);
    }
}
