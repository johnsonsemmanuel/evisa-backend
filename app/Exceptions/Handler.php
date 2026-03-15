<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Exceptions\BackedEnumCaseNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ThrottleRequestsException;
use Throwable;

/**
 * Government-Grade Exception Handler
 * 
 * Implements OWASP A05:2021 — Security Misconfiguration mitigation.
 * 
 * SECURITY RULES:
 * 1. NEVER expose stack traces, file paths, or internal class names in production
 * 2. All API errors return consistent JSON structure
 * 3. Error codes logged server-side but never exposed to client
 * 4. Unique error reference IDs for log correlation
 * 
 * @package App\Exceptions
 */
class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
        'pin',
        'token',
        'secret',
        'api_key',
        'access_token',
        'refresh_token',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Additional reporting logic can go here
        });
    }

    /**
     * Render an exception into an HTTP response.
     * 
     * SECURITY: Government-grade error handling with information disclosure prevention
     */
    public function render($request, Throwable $e): Response
    {
        // SECURITY CHECK: Disable debug mode in production
        if (config('app.debug') && !app()->environment('production')) {
            return parent::render($request, $e);
        }

        // Handle API requests with secure JSON responses
        if ($request->expectsJson() || $request->is('api/*')) {
            return $this->renderApiException($request, $e);
        }

        // For web requests, use parent handler but ensure no sensitive info leaks
        return parent::render($request, $e);
    }

    /**
     * Render API exceptions with government-grade security
     * 
     * SECURITY: Never expose internal details, always provide error reference
     */
    private function renderApiException(Request $request, Throwable $e): JsonResponse
    {
        // Generate unique error reference ID for log correlation
        $errorRef = Str::upper(Str::random(8));

        // Log full exception details server-side with reference ID
        Log::error("API Exception [{$errorRef}]", [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'user_id' => Auth::id(),
            'route' => $request->path(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String(),
            // NOTE: Do NOT log request->all() - may contain PII/passwords
        ]);

        // Map exception types to safe public responses
        return match(true) {
            // Validation errors - safe to expose field-level messages
            $e instanceof ValidationException => response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'code' => 'ERR_VALIDATION',
                'errors' => $e->errors(),
                'ref' => $errorRef,
            ], 422),

            // Authentication required
            $e instanceof AuthenticationException => response()->json([
                'status' => 'error',
                'message' => 'Authentication required',
                'code' => 'ERR_AUTH',
                'ref' => $errorRef,
            ], 401),

            // Authorization/permission denied
            $e instanceof AuthorizationException => response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to perform this action',
                'code' => 'ERR_FORBIDDEN',
                'ref' => $errorRef,
            ], 403),

            // Model/resource not found
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => response()->json([
                'status' => 'error',
                'message' => 'The requested resource was not found',
                'code' => 'ERR_NOT_FOUND',
                'ref' => $errorRef,
            ], 404),

            // Rate limiting
            $e instanceof ThrottleRequestsException => response()->json([
                'status' => 'error',
                'message' => 'Too many requests. Please slow down.',
                'code' => 'ERR_RATE_LIMIT',
                'ref' => $errorRef,
            ], 429),

            // HTTP exceptions with status codes
            $e instanceof HttpException => response()->json([
                'status' => 'error',
                'message' => $this->getHttpExceptionMessage($e),
                'code' => $this->getHttpExceptionCode($e),
                'ref' => $errorRef,
            ], $e->getStatusCode()),

            // Custom application exceptions
            $e instanceof InvalidFileContentException => response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'code' => 'ERR_INVALID_FILE',
                'ref' => $errorRef,
            ], 422),

            $e instanceof MaliciousFileException => response()->json([
                'status' => 'error',
                'message' => 'File upload rejected for security reasons',
                'code' => 'ERR_SECURITY',
                'ref' => $errorRef,
            ], 403),

            $e instanceof FeeNotFoundException => response()->json([
                'status' => 'error',
                'message' => 'Fee information not available',
                'code' => 'ERR_FEE_NOT_FOUND',
                'ref' => $errorRef,
            ], 404),

            $e instanceof PaymentAmountMismatchException => response()->json([
                'status' => 'error',
                'message' => 'Payment amount does not match expected fee',
                'code' => 'ERR_PAYMENT_AMOUNT',
                'ref' => $errorRef,
            ], 422),

            $e instanceof PaymentNotAllowedException => response()->json([
                'status' => 'error',
                'message' => 'Payment not allowed for this application',
                'code' => 'ERR_PAYMENT_NOT_ALLOWED',
                'ref' => $errorRef,
            ], 403),

            $e instanceof WebhookVerificationException => response()->json([
                'status' => 'error',
                'message' => 'Webhook verification failed',
                'code' => 'ERR_WEBHOOK_VERIFICATION',
                'ref' => $errorRef,
            ], 401),

            // Enum case not found (Laravel 11)
            $e instanceof BackedEnumCaseNotFoundException => response()->json([
                'status' => 'error',
                'message' => 'Invalid value provided',
                'code' => 'ERR_INVALID_VALUE',
                'ref' => $errorRef,
            ], 422),

            // ALL other exceptions - NEVER reveal internal details
            default => response()->json([
                'status' => 'error',
                'message' => 'An internal error occurred. Reference: ' . $errorRef,
                'code' => 'ERR_INTERNAL',
                'ref' => $errorRef,
            ], 500),
        };
    }

    /**
     * Get safe HTTP exception message
     */
    private function getHttpExceptionMessage(HttpException $e): string
    {
        return match($e->getStatusCode()) {
            400 => 'Bad request',
            401 => 'Authentication required',
            403 => 'Access forbidden',
            404 => 'Resource not found',
            405 => 'Method not allowed',
            408 => 'Request timeout',
            409 => 'Conflict',
            410 => 'Resource no longer available',
            413 => 'Request too large',
            415 => 'Unsupported media type',
            422 => 'Unprocessable entity',
            429 => 'Too many requests',
            500 => 'Internal server error',
            502 => 'Bad gateway',
            503 => 'Service unavailable',
            504 => 'Gateway timeout',
            default => 'An error occurred',
        };
    }

    /**
     * Get safe HTTP exception code
     */
    private function getHttpExceptionCode(HttpException $e): string
    {
        return match($e->getStatusCode()) {
            400 => 'ERR_BAD_REQUEST',
            401 => 'ERR_AUTH',
            403 => 'ERR_FORBIDDEN',
            404 => 'ERR_NOT_FOUND',
            405 => 'ERR_METHOD_NOT_ALLOWED',
            408 => 'ERR_TIMEOUT',
            409 => 'ERR_CONFLICT',
            410 => 'ERR_GONE',
            413 => 'ERR_TOO_LARGE',
            415 => 'ERR_UNSUPPORTED_MEDIA',
            422 => 'ERR_UNPROCESSABLE',
            429 => 'ERR_RATE_LIMIT',
            500 => 'ERR_INTERNAL',
            502 => 'ERR_BAD_GATEWAY',
            503 => 'ERR_UNAVAILABLE',
            504 => 'ERR_GATEWAY_TIMEOUT',
            default => 'ERR_HTTP',
        };
    }
}