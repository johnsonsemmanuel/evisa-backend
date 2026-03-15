<?php

namespace App\Providers;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\Payment;
use App\Observers\PaymentObserver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // SECURITY CHECK: Ensure APP_DEBUG is false in production
        if (app()->environment('production') && config('app.debug')) {
            throw new \RuntimeException(
                'SECURITY VIOLATION: APP_DEBUG must be false in production environment. ' .
                'This prevents sensitive information disclosure in error responses.'
            );
        }

        // SECURITY: Force HTTPS in production
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        // DEVELOPMENT: Query logging for N+1 detection
        if (app()->environment('local')) {
            DB::listen(function ($query) {
                if ($query->time > 100) {
                    Log::warning('Slow query detected', [
                        'sql' => $query->sql,
                        'bindings' => $query->bindings,
                        'time' => $query->time . 'ms',
                    ]);
                }
            });
        }

        // PRODUCTION: Log slow queries (> 1 second) to payment channel — bindings never logged (may contain PII)
        if (app()->environment('production')) {
            DB::listen(function ($query) {
                if ($query->time > 1000) {
                    Log::channel('payment')->warning('Slow database query', [
                        'sql' => $query->sql,
                        'bindings' => '***REDACTED***',
                        'time_ms' => $query->time,
                    ]);
                }
            });
        }

        // Register Payment Observer for audit trail
        Payment::observe(PaymentObserver::class);

        // Register Application Observer for cache busting
        Application::observe(\App\Observers\ApplicationObserver::class);

        // ========================================
        // LAYER 1: Route Model Binding with Ownership Enforcement
        // SECURITY: Prevents IDOR attacks at the route binding level
        // ========================================

        /**
         * Application Route Binding with Ownership Check
         * 
         * SECURITY: For applicants, verify they own the application before binding.
         * For officers, verify the application is in their jurisdiction.
         * This prevents IDOR attacks where users manipulate application IDs in URLs.
         */
        Route::bind('application', function ($value) {
            $application = Application::withoutGlobalScopes()->findOrFail($value);
            
            if (!Auth::check()) {
                abort(401, 'Unauthenticated');
            }

            $user = Auth::user();

            // APPLICANT: Must own the application
            if ($user->role === 'applicant') {
                if ($application->user_id !== $user->id) {
                    Log::warning('IDOR ATTEMPT: Applicant tried to access another user\'s application', [
                        'user_id' => $user->id,
                        'user_email' => $user->email,
                        'attempted_application_id' => $application->id,
                        'application_owner_id' => $application->user_id,
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                        'route' => request()->route()->getName(),
                        'method' => request()->method(),
                        'url' => request()->fullUrl(),
                        'timestamp' => now()->toISOString(),
                    ]);
                    
                    abort(403, 'Access denied: You do not have permission to access this application');
                }
            }

            // GIS OFFICERS: Must be assigned to GIS
            elseif (in_array($user->role, ['gis_officer', 'gis_reviewer', 'gis_approver', 'gis_admin', 'GIS_REVIEWING_OFFICER', 'GIS_APPROVAL_OFFICER', 'GIS_ADMIN'])) {
                if ($application->assigned_agency !== 'gis') {
                    Log::warning('IDOR ATTEMPT: GIS officer tried to access non-GIS application', [
                        'user_id' => $user->id,
                        'user_role' => $user->role,
                        'attempted_application_id' => $application->id,
                        'application_agency' => $application->assigned_agency,
                        'ip_address' => request()->ip(),
                        'timestamp' => now()->toISOString(),
                    ]);
                    
                    abort(403, 'Access denied: This application is not assigned to your agency');
                }
            }

            // MFA OFFICERS: Must be assigned to MFA and their mission
            elseif (in_array($user->role, ['mfa_reviewer', 'mfa_approver', 'mfa_admin', 'MFA_REVIEWING_OFFICER', 'MFA_APPROVAL_OFFICER', 'MFA_ADMIN'])) {
                if ($application->assigned_agency !== 'mfa') {
                    Log::warning('IDOR ATTEMPT: MFA officer tried to access non-MFA application', [
                        'user_id' => $user->id,
                        'user_role' => $user->role,
                        'attempted_application_id' => $application->id,
                        'application_agency' => $application->assigned_agency,
                        'ip_address' => request()->ip(),
                        'timestamp' => now()->toISOString(),
                    ]);
                    
                    abort(403, 'Access denied: This application is not assigned to MFA');
                }

                // Further check mission assignment (unless MFA admin)
                if (!in_array($user->role, ['mfa_admin', 'MFA_ADMIN']) && $user->mfa_mission_id) {
                    if ($application->owner_mission_id !== $user->mfa_mission_id) {
                        Log::warning('IDOR ATTEMPT: MFA officer tried to access application from different mission', [
                            'user_id' => $user->id,
                            'user_mission_id' => $user->mfa_mission_id,
                            'attempted_application_id' => $application->id,
                            'application_mission_id' => $application->owner_mission_id,
                            'ip_address' => request()->ip(),
                            'timestamp' => now()->toISOString(),
                        ]);
                        
                        abort(403, 'Access denied: This application is assigned to a different mission');
                    }
                }
            }

            // BORDER OFFICERS: Only approved/issued applications
            elseif ($user->role === 'border_officer') {
                if (!in_array($application->status, ['approved', 'issued'])) {
                    Log::warning('IDOR ATTEMPT: Border officer tried to access non-approved application', [
                        'user_id' => $user->id,
                        'attempted_application_id' => $application->id,
                        'application_status' => $application->status,
                        'ip_address' => request()->ip(),
                        'timestamp' => now()->toISOString(),
                    ]);
                    
                    abort(403, 'Access denied: Only approved applications are accessible');
                }
            }

            // AIRLINE STAFF: Only approved/issued applications
            elseif ($user->role === 'airline_staff') {
                if (!in_array($application->status, ['approved', 'issued'])) {
                    abort(403, 'Access denied: Only approved applications are accessible');
                }
            }

            // FINANCE OFFICERS and ADMINS: No restrictions at binding level
            // (Policies will handle specific operation permissions)

            return $application;
        });

        /**
         * Payment Route Binding with Ownership Check
         * 
         * SECURITY: Verify payment belongs to authenticated user or is accessible by their role.
         */
        Route::bind('payment', function ($value) {
            $payment = Payment::findOrFail($value);
            
            if (!Auth::check()) {
                abort(401, 'Unauthenticated');
            }

            $user = Auth::user();

            // APPLICANT: Must own the payment
            if ($user->role === 'applicant') {
                if ($payment->user_id !== $user->id) {
                    Log::warning('IDOR ATTEMPT: Applicant tried to access another user\'s payment', [
                        'user_id' => $user->id,
                        'attempted_payment_id' => $payment->id,
                        'payment_owner_id' => $payment->user_id,
                        'ip_address' => request()->ip(),
                        'timestamp' => now()->toISOString(),
                    ]);
                    
                    abort(403, 'Access denied: You do not have permission to access this payment');
                }
            }

            // FINANCE OFFICERS and ADMINS: Can access all payments
            // Other officers: No direct payment access (must go through application)

            return $payment;
        });

        /**
         * ApplicationDocument Route Binding with Ownership Check
         * 
         * SECURITY: Verify document belongs to an application the user can access.
         */
        Route::bind('document', function ($value) {
            $document = ApplicationDocument::findOrFail($value);
            
            if (!Auth::check()) {
                abort(401, 'Unauthenticated');
            }

            $user = Auth::user();
            $application = $document->application;

            // APPLICANT: Must own the application
            if ($user->role === 'applicant') {
                if ($application->user_id !== $user->id) {
                    Log::warning('IDOR ATTEMPT: Applicant tried to access document from another user\'s application', [
                        'user_id' => $user->id,
                        'attempted_document_id' => $document->id,
                        'application_id' => $application->id,
                        'application_owner_id' => $application->user_id,
                        'ip_address' => request()->ip(),
                        'timestamp' => now()->toISOString(),
                    ]);
                    
                    abort(403, 'Access denied: You do not have permission to access this document');
                }
            }

            // OFFICERS: Apply same agency/mission checks as applications
            elseif (in_array($user->role, ['gis_officer', 'gis_reviewer', 'gis_approver', 'gis_admin', 'GIS_REVIEWING_OFFICER', 'GIS_APPROVAL_OFFICER', 'GIS_ADMIN'])) {
                if ($application->assigned_agency !== 'gis') {
                    abort(403, 'Access denied: This document belongs to an application not assigned to your agency');
                }
            }
            elseif (in_array($user->role, ['mfa_reviewer', 'mfa_approver', 'mfa_admin', 'MFA_REVIEWING_OFFICER', 'MFA_APPROVAL_OFFICER', 'MFA_ADMIN'])) {
                if ($application->assigned_agency !== 'mfa') {
                    abort(403, 'Access denied: This document belongs to an application not assigned to MFA');
                }
                
                if (!in_array($user->role, ['mfa_admin', 'MFA_ADMIN']) && $user->mfa_mission_id) {
                    if ($application->owner_mission_id !== $user->mfa_mission_id) {
                        abort(403, 'Access denied: This document belongs to an application from a different mission');
                    }
                }
            }

            return $document;
        });

        // ========================================
        // LAYER 2: Rate Limiting for Login Endpoint
        // SECURITY: Additional protection against brute force attacks
        // ========================================
        
        /**
         * Login Rate Limiter
         * 
         * SECURITY: Provides a second layer of protection beyond the LoginAttemptService.
         * This is IP-based throttling that works even if Redis is unavailable.
         */
        RateLimiter::for('login', function ($request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
