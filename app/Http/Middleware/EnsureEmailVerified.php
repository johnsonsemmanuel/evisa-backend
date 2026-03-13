<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailVerified
{
    /**
     * Handle an incoming request.
     * Ensures the user has verified their email before proceeding.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        // Staff users (GIS, MFA, Admin) don't need email verification
        if (in_array($user->role, ['gis_officer', 'mfa_reviewer', 'admin'])) {
            return $next($request);
        }

        // Applicants need email verification to create applications
        if ($user->role === 'applicant' && !$user->email_verified_at) {
            return response()->json([
                'message' => 'Please verify your email address before creating applications.',
                'requires_verification' => true,
            ], 403);
        }

        return $next($request);
    }
}
