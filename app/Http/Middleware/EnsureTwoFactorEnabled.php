<?php

namespace App\Http\Middleware;

use App\Services\TwoFactorAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureTwoFactorEnabled Middleware
 * 
 * Enforces 2FA setup for officer and admin roles.
 * Applied to all protected routes that require 2FA.
 * 
 * SECURITY: Prevents officers/admins from accessing the system without 2FA.
 * Required by NIST SP 800-53 IA-2.
 */
class EnsureTwoFactorEnabled
{
    public function __construct(
        protected TwoFactorAuthService $twoFactorService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Skip if user is not authenticated
        if (!$user) {
            return $next($request);
        }

        // Check if user's role requires 2FA
        if (!$this->twoFactorService->isRoleRequired($user)) {
            return $next($request);
        }

        // Check if 2FA is enabled and confirmed
        if ($this->twoFactorService->isEnabled($user)) {
            return $next($request);
        }

        // 2FA is required but not set up - block access
        return response()->json([
            'message' => '2FA setup required',
            'error' => 'Your role requires Multi-Factor Authentication. Please set up 2FA before accessing the system.',
            'setup_url' => '/api/auth/2fa/setup',
            'required' => true,
        ], 403);
    }
}
