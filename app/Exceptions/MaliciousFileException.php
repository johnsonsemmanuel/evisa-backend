<?php

namespace App\Exceptions;

use Exception;

/**
 * MaliciousFileException
 * 
 * Thrown when malicious content is detected in uploaded file.
 * This is a CRITICAL security event and should trigger alerts.
 * 
 * @package App\Exceptions
 */
class MaliciousFileException extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(string $message = 'Malicious file detected', int $code = 403, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Report the exception.
     *
     * @return void
     */
    public function report()
    {
        // Log to security channel
        \Log::channel('security')->critical('Malicious file upload attempt', [
            'message' => $this->getMessage(),
            'ip_address' => request()->ip(),
            'user_id' => auth()->id(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ]);

        // In production, send alert to security team
        // Notification::route('mail', config('app.security_email'))
        //     ->notify(new MaliciousFileUploadAttemptNotification(...));
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
            'error' => 'malicious_file_detected',
        ], $this->code);
    }
}
