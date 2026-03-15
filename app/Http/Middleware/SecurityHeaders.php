<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Government-Grade Security Headers Middleware
 * Implements OWASP recommended security headers for defense-in-depth.
 */
class SecurityHeaders
{
    /**
     * Security headers to apply to all responses.
     */
    protected array $headers = [
        // Prevent XSS attacks
        'X-XSS-Protection' => '1; mode=block',
        
        // Prevent MIME type sniffing
        'X-Content-Type-Options' => 'nosniff',
        
        // Prevent clickjacking
        'X-Frame-Options' => 'DENY',
        
        // Referrer policy for privacy
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        
        // Permissions policy (formerly Feature-Policy)
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(), payment=()',
        
        // Cache control for sensitive data
        'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
        'Pragma' => 'no-cache',
        
        // Prevent information disclosure
        'X-Powered-By' => '',
        'Server' => '',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Apply security headers
        foreach ($this->headers as $key => $value) {
            if ($value === '') {
                $response->headers->remove($key);
            } else {
                $response->headers->set($key, $value);
            }
        }

        // Content Security Policy (CSP)
        $response->headers->set(
            'Content-Security-Policy',
            $this->buildCsp()
        );

        // Strict Transport Security (HSTS) - only in production
        if (app()->environment('production')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        return $response;
    }

    /**
     * Build Content Security Policy header.
     */
    protected function buildCsp(): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self' " . config('app.frontend_url', 'http://localhost:3000'),
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ];

        return implode('; ', $directives);
    }
}
