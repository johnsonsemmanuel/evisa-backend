<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Government-Grade Input Sanitization Middleware
 * Protects against SQL Injection, XSS, Command Injection, and Parameter Tampering.
 */
class InputSanitization
{
    /**
     * Dangerous patterns to detect and block.
     */
    protected array $dangerousPatterns = [
        // SQL Injection patterns
        '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|UNION|ALTER|CREATE|TRUNCATE)\b.*\b(FROM|INTO|TABLE|DATABASE)\b)/i',
        '/(\b(OR|AND)\b\s+[\'\"]?\d+[\'\"]?\s*=\s*[\'\"]?\d+[\'\"]?)/i',
        '/(\'|\")(\s*)(OR|AND)(\s*)(\'|\"|\d)/i',
        '/(\-\-|\/\*|\*\/|;)/i',
        
        // Command Injection patterns
        '/(\||;|`|\$\(|&&|\|\|)/i',
        '/(\/bin\/|\/etc\/|\/usr\/|cmd\.exe|powershell)/i',
        
        // Path Traversal patterns
        '/(\.\.\/|\.\.\\\\|%2e%2e%2f|%2e%2e\/|\.\.%2f)/i',
        
        // XSS patterns (basic - more handled by output encoding)
        '/<script[^>]*>.*?<\/script>/is',
        '/javascript\s*:/i',
        '/on\w+\s*=/i',
    ];

    /**
     * Fields to skip sanitization (binary data, etc.)
     */
    protected array $skipFields = [
        'file',
        'document',
        'image',
        'attachment',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check for dangerous patterns in input
        $input = $request->all();
        $violations = $this->detectViolations($input);

        if (!empty($violations)) {
            \Log::channel('security')->warning('Malicious input detected', [
                'ip_address' => $request->ip(),
                'user_id' => $request->user()?->id,
                'path' => $request->path(),
                'method' => $request->method(),
                'violations' => $violations,
            ]);

            return response()->json([
                'message' => 'Invalid input detected. Request blocked for security reasons.',
                'error' => 'MALICIOUS_INPUT_DETECTED',
            ], 400);
        }

        // Sanitize input
        $sanitized = $this->sanitizeInput($input);
        $request->merge($sanitized);

        return $next($request);
    }

    /**
     * Detect dangerous patterns in input.
     */
    protected function detectViolations(array $input, string $prefix = ''): array
    {
        $violations = [];

        foreach ($input as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (in_array($key, $this->skipFields)) {
                continue;
            }

            if (is_array($value)) {
                $violations = array_merge($violations, $this->detectViolations($value, $fullKey));
            } elseif (is_string($value)) {
                foreach ($this->dangerousPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $violations[] = [
                            'field' => $fullKey,
                            'pattern' => 'dangerous_input',
                            'value_preview' => substr($value, 0, 50) . '...',
                        ];
                        break;
                    }
                }
            }
        }

        return $violations;
    }

    /**
     * Sanitize input values.
     */
    protected function sanitizeInput(array $input): array
    {
        $sanitized = [];

        foreach ($input as $key => $value) {
            if (in_array($key, $this->skipFields)) {
                $sanitized[$key] = $value;
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeInput($value);
            } elseif (is_string($value)) {
                $sanitized[$key] = $this->sanitizeString($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize a string value.
     */
    protected function sanitizeString(string $value): string
    {
        // Trim whitespace
        $value = trim($value);

        // Remove null bytes
        $value = str_replace(chr(0), '', $value);

        // Normalize line endings
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        // Remove control characters (except newline and tab)
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);

        return $value;
    }
}
