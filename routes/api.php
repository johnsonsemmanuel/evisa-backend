<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\AeropassController;
use App\Http\Controllers\Api\Applicant\ApplicationController;
use App\Http\Controllers\Api\Applicant\DocumentController;
use App\Http\Controllers\Api\GIS\CaseController;
use App\Http\Controllers\Api\GIS\CaseQueueController;
use App\Http\Controllers\Api\GIS\CaseUtilityController;
use App\Http\Controllers\Api\GIS\CaseReviewController;
use App\Http\Controllers\Api\GIS\CaseDecisionController;
use App\Http\Controllers\Api\GIS\CaseBatchController;
use App\Http\Controllers\Api\MFA\EscalationController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\Admin\TierConfigController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\ServiceTierController;
use App\Http\Controllers\Api\Admin\ReasonCodeController;
use App\Http\Controllers\Api\VerificationController;
use App\Http\Controllers\Api\PricingController;
use Illuminate\Support\Facades\Route;

// Health check endpoint for Railway
Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'timestamp' => now()]);
});

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->middleware('auth.errors')->group(function () {
    // Rate limit login attempts: More generous limits to prevent false positives
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/register', [AuthController::class, 'register'])->middleware('robust.throttle:5,1');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->middleware('robust.throttle:5,1');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('robust.throttle:10,1');
    
    // 2FA verification (no auth required - uses two_factor_token)
    Route::post('/2fa/verify', [\App\Http\Controllers\Api\Auth\TwoFactorAuthController::class, 'verify'])
        ->middleware('robust.throttle:5,1');
});

/*
|--------------------------------------------------------------------------
| Payment Webhooks (External Provider Callbacks)
|--------------------------------------------------------------------------
|
| These routes handle payment gateway callbacks/webhooks.
| SECURITY:
| - No auth middleware (verified via provider signature)
| - Excluded from CSRF protection (external POST requests)
| - Higher rate limit for legitimate webhook traffic
| - Signature verification enforced in controller
|
*/
Route::prefix('webhooks')->group(function () {
    Route::post('/payment', [WebhookController::class, 'handlePayment'])
        ->middleware('throttle:300,1');

    Route::post('/gcb', [WebhookController::class, 'handleGcbCallback'])
        ->middleware('throttle:300,1');

    Route::post('/paystack', [WebhookController::class, 'handlePaystackWebhook'])
        ->middleware('throttle:300,1');
});

// GCB callback (primary route — no auth, signature verified in controller)
Route::post('/gcb/callback', [\App\Http\Controllers\Api\GCBCallbackController::class, '__invoke'])
    ->name('gcb.callback')
    ->middleware('throttle:300,1');

/*
|--------------------------------------------------------------------------
| Aeropass API Routes (External Integration)
|--------------------------------------------------------------------------
*/

// Interpol nominal verification callback from Aeropass (legacy sync flow)
Route::post('/aeropass/interpol-nominal-verification/callback', [AeropassController::class, 'interpolCallback'])
    ->middleware('throttle:100,1');

// Async nominal check result callback (Aeropass POSTs here; no auth — verify HMAC in controller)
Route::post('/aeropass/callback', [\App\Http\Controllers\Api\AeropassCallbackController::class, '__invoke'])
    ->name('aeropass.callback')
    ->middleware('throttle:300,1');

// E-Visa check endpoint for Aeropass
Route::post('/aeropass/visa-check', [\App\Http\Controllers\Api\AeropassController::class, 'visaCheck'])
    ->middleware('throttle:100,1'); // Higher limit for external system

// Public: available visa types - moderate rate limiting
Route::get('/visa-types', [ApplicationController::class, 'visaTypes'])->middleware('throttle:60,1');

// Public: service tiers for fee calculation - moderate rate limiting
Route::get('/service-tiers', function () {
    return response()->json(['service_tiers' => \App\Models\ServiceTier::active()->ordered()->get()]);
})->middleware('throttle:60,1');

// Public: pricing calculation endpoints
Route::prefix('pricing')->middleware('throttle:60,1')->group(function () {
    Route::post('/calculate', [PricingController::class, 'calculatePrice']);
    Route::get('/tiers', [PricingController::class, 'getServiceTiers']);
    Route::get('/examples', [PricingController::class, 'getExamples']);
});

// Public: track application by reference number - strict rate limiting to prevent abuse
Route::post('/track', [ApplicationController::class, 'track'])->middleware('throttle:30,1');

// Public: two-step tracking with SMS OTP verification
Route::prefix('tracking')->middleware('throttle:10,1')->group(function () {
    Route::post('/initiate', [\App\Http\Controllers\Api\TrackingController::class, 'initiate']);
    Route::post('/verify', [\App\Http\Controllers\Api\TrackingController::class, 'verify']);
});

// Public: eVisa verification endpoints (for Border/Airline Portal)
// Rate limited to prevent abuse - 30 requests per minute
Route::prefix('verify')->middleware('throttle:30,1')->group(function () {
    Route::post('/evisa', [VerificationController::class, 'validateEvisa']);
    Route::get('/status/{referenceNumber}', [VerificationController::class, 'getStatus']);
});

// Public: QR code verification for border officers
Route::get('/verify/{code}', [\App\Http\Controllers\Api\VerifyController::class, 'verify'])->middleware('throttle:60,1');

// Public: ETA (Electronic Travel Authorization) endpoints
Route::prefix('eta')->group(function () {
    Route::post('/check-eligibility', [\App\Http\Controllers\Api\EtaController::class, 'checkEligibility'])->middleware('throttle:60,1');
    Route::get('/issuing-authorities', [\App\Http\Controllers\Api\EtaController::class, 'getIssuingAuthorities'])->middleware('throttle:60,1');
    Route::post('/validate-passport', [\App\Http\Controllers\Api\EtaController::class, 'validatePassportNumber'])->middleware('throttle:60,1');
    Route::get('/eligible', [\App\Http\Controllers\Api\EtaController::class, 'eligibleTypes']);
    Route::post('/apply', [\App\Http\Controllers\Api\EtaController::class, 'apply'])->middleware('throttle:10,1');
    Route::post('/status', [\App\Http\Controllers\Api\EtaController::class, 'status'])->middleware('throttle:30,1');
    Route::post('/verify', [\App\Http\Controllers\Api\EtaController::class, 'verify'])->middleware('throttle:30,1');
    Route::post('/payment-callback', [\App\Http\Controllers\Api\EtaController::class, 'paymentCallback']);
});

// Border Control endpoints (for immigration officers at ports)
Route::prefix('border')->middleware('throttle:60,1')->group(function () {
    Route::post('/verify', [\App\Http\Controllers\Api\BorderController::class, 'verify']);
    Route::post('/verify-qr', [\App\Http\Controllers\Api\BorderController::class, 'verifyQr']);
    Route::post('/quick-scan', [\App\Http\Controllers\Api\BorderController::class, 'quickScan']);
    Route::get('/ports', [\App\Http\Controllers\Api\BorderController::class, 'ports']);
});

// Sumsub Integration endpoints
Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('sumsub')->group(function () {
    Route::post('/generate-token', [\App\Http\Controllers\Api\SumsubController::class, 'generateToken']);
    Route::get('/status/{applicantId}', [\App\Http\Controllers\Api\SumsubController::class, 'getStatus']);
    Route::post('/update-applicant-id', [\App\Http\Controllers\Api\SumsubController::class, 'updateApplicantId']);
    Route::get('/statistics', [\App\Http\Controllers\Api\SumsubController::class, 'getStatistics'])
        ->middleware(\App\Http\Middleware\EnsureRole::class . ':admin,gis_admin,mfa_reviewer');
});

// Sumsub webhook (no auth, no CSRF — verified via X-App-Token + HMAC in controller)
Route::post('/sumsub/webhook', [\App\Http\Controllers\Api\SumsubWebhookController::class, '__invoke'])
    ->middleware('throttle:300,1');

// Authenticated Border Control routes (Week 2: Border Verification System)
Route::middleware(['auth:sanctum', 'throttle:60,1'])->prefix('border')->group(function () {
    // New Border Verification API endpoints
    Route::post('/verify-travel', [\App\Http\Controllers\Api\BorderController::class, 'verifyTravel']);
    Route::post('/generate-bac', [\App\Http\Controllers\Api\BorderController::class, 'generateBac']);
    Route::get('/verify-bac/{code}', [\App\Http\Controllers\Api\BorderController::class, 'verifyBac']);
    
    // Immigration officer only - entry confirmation
    Route::post('/confirm-entry', [\App\Http\Controllers\Api\BorderController::class, 'confirmEntry'])
        ->middleware(\App\Http\Middleware\EnsureRole::class . ':immigration_officer,admin');
    
    // Legacy endpoints (keep for backward compatibility)
    Route::post('/crossing', [\App\Http\Controllers\Api\BorderController::class, 'recordCrossing']);
    Route::post('/record', [\App\Http\Controllers\Api\BorderController::class, 'recordCrossing']);
    Route::post('/verify-and-record', [\App\Http\Controllers\Api\BorderController::class, 'verifyAndRecordEntry']);
    Route::get('/offline-cache', [\App\Http\Controllers\Api\BorderController::class, 'offlineCache']);
    Route::get('/statistics', [\App\Http\Controllers\Api\BorderController::class, 'statistics']);
    Route::get('/recent', [\App\Http\Controllers\Api\BorderController::class, 'recentCrossings']);
    
    // Reporting endpoints for HQ
    Route::get('/reports/arrivals', [\App\Http\Controllers\Api\BorderController::class, 'arrivalsReport']);
    Route::get('/reports/outcomes', [\App\Http\Controllers\Api\BorderController::class, 'outcomesReport']);
    Route::get('/reports/alerts', [\App\Http\Controllers\Api\BorderController::class, 'alertsReport']);
    Route::get('/reports/productivity', [\App\Http\Controllers\Api\BorderController::class, 'productivityReport']);
    Route::get('/reports/exceptions', [\App\Http\Controllers\Api\BorderController::class, 'exceptionsReport']);
});

// Airline API endpoints (for airlines to verify passenger travel authorization)
// Rate limited: 100 requests per minute per API key
Route::prefix('airline')->middleware('throttle:100,1')->group(function () {
    Route::post('/verify-passenger', [\App\Http\Controllers\Api\AirlineController::class, 'verifyPassenger']);
    Route::post('/verify-qr', [\App\Http\Controllers\Api\AirlineController::class, 'verifyQrCode']);
    Route::post('/batch-verify', [\App\Http\Controllers\Api\AirlineController::class, 'batchVerify']);
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'api.error', \App\Http\Middleware\SetLocale::class, \App\Http\Middleware\AuditAction::class, 'throttle:60,1'])->group(function () {

    // ── Broadcasting auth (Pusher private/presence channel auth)
    Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
        return \Illuminate\Support\Facades\Broadcast::auth($request);
    })->name('broadcasting.auth');

    // ── Auth & Profile ──────────────────────────────────────────────
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password', [AuthController::class, 'changePassword']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // ── Two-Factor Authentication ───────────────────────────────────
    Route::prefix('auth/2fa')->group(function () {
        Route::post('/setup', [\App\Http\Controllers\Api\Auth\TwoFactorAuthController::class, 'setup']);
        Route::post('/confirm', [\App\Http\Controllers\Api\Auth\TwoFactorAuthController::class, 'confirm']);
        Route::get('/status', [\App\Http\Controllers\Api\Auth\TwoFactorAuthController::class, 'status']);
        Route::get('/recovery-codes', [\App\Http\Controllers\Api\Auth\TwoFactorAuthController::class, 'getRecoveryCodes']);
        Route::post('/recovery-codes/regenerate', [\App\Http\Controllers\Api\Auth\TwoFactorAuthController::class, 'regenerateRecoveryCodes']);
        Route::post('/disable', [\App\Http\Controllers\Api\Auth\TwoFactorAuthController::class, 'disable']);
    });

    /*
    |----------------------------------------------------------------------
    | Applicant Routes
    |----------------------------------------------------------------------
    */
    Route::middleware([\App\Http\Middleware\EnsureRole::class . ':applicant'])->prefix('applicant')->group(function () {

        Route::get('/applications', [ApplicationController::class, 'index']);
        Route::post('/applications', [ApplicationController::class, 'store']);
        Route::get('/applications/{application}', [ApplicationController::class, 'show']);
        Route::put('/applications/{application}', [ApplicationController::class, 'update']);
        Route::delete('/applications/{application}', [ApplicationController::class, 'destroy']);
        Route::put('/applications/{application}/step', [ApplicationController::class, 'updateStep']);
        Route::post('/applications/{application}/submit', [ApplicationController::class, 'submit']);
        Route::post('/applications/{application}/submit-without-payment', [ApplicationController::class, 'submitWithoutPayment']);
        Route::post('/applications/{application}/submit-response', [ApplicationController::class, 'submitResponse']);
        Route::post('/applications/{application}/pay', [ApplicationController::class, 'initiatePayment']);
        Route::get('/applications/{application}/status', [ApplicationController::class, 'status']);
        Route::get('/applications/{application}/evisa', [ApplicationController::class, 'downloadEvisa']);

        // Documents - SECURE FILE UPLOAD & SERVING
        Route::post('/applications/{application}/documents', [DocumentController::class, 'upload']);
        Route::post('/documents/{document}/reupload', [DocumentController::class, 'reupload']);
        Route::get('/applications/{application}/documents/check', [DocumentController::class, 'checkCompleteness']);
        Route::get('/applications/{application}/documents', [ApplicationController::class, 'documents']);
        
        // Secure document serving via signed URLs
        Route::get('/documents/{document}/download-url', [DocumentController::class, 'getDownloadUrl'])
            ->name('documents.download-url');
        Route::get('/documents/{document}/serve', [DocumentController::class, 'serve'])
            ->name('documents.serve');
        
        // Legacy download route (deprecated - use signed URLs)
        Route::get('/applications/{application}/documents/{document}/download', [ApplicationController::class, 'downloadDocument']);
        Route::delete('/applications/{application}/documents/{document}', [ApplicationController::class, 'deleteDocument']);

        // Payments - HARDENED SECURITY
        Route::prefix('payments')->middleware(['payment.rate.limit'])->group(function () {
            // Payment methods (low risk, standard throttling)
            Route::get('/methods', [\App\Http\Controllers\Api\PaymentController::class, 'methods'])
                ->middleware('throttle:60,1');
            
            // Payment initiation (high risk, strict throttling)
            Route::post('/applications/{application}/initialize', [\App\Http\Controllers\Api\PaymentController::class, 'initialize'])
                ->middleware('throttle:5,1');
            
            // Payment verification (medium risk)
            Route::post('/verify', [\App\Http\Controllers\Api\Applicant\PaymentController::class, 'verify'])
                ->middleware('throttle:30,1');
            
            // Payment status checks (medium risk)
            Route::get('/applications/{application}/history', [\App\Http\Controllers\Api\PaymentController::class, 'history'])
                ->middleware('throttle:30,1');
            
            // Payment proof upload (medium risk)
            Route::post('/upload-proof', [\App\Http\Controllers\Api\PaymentController::class, 'uploadProof'])
                ->middleware('throttle:10,1');
            
            // Test endpoints (development only, strict throttling)
            Route::post('/simulate', [\App\Http\Controllers\Api\Applicant\PaymentController::class, 'simulatePayment'])
                ->middleware('throttle:5,1');
            Route::post('/test-callback', [\App\Http\Controllers\Api\PaymentController::class, 'testPaymentCallback'])
                ->middleware('throttle:5,1');
        });

        // OCR - Extract passport data
        Route::post('/ocr/extract-passport', [\App\Http\Controllers\Api\Applicant\OcrController::class, 'extractPassportData']);

        // Passport verification
        Route::post('/verify-passport', [ApplicationController::class, 'verifyPassport'])->middleware('throttle:10,1');

        // Application Preview
        Route::get('/applications/{application}/preview', [\App\Http\Controllers\Api\Applicant\PreviewController::class, 'getPreview']);
        Route::get('/applications/{application}/preview/pdf', [\App\Http\Controllers\Api\Applicant\PreviewController::class, 'downloadPreview']);

        // Notifications
        Route::get('/notifications', [\App\Http\Controllers\Api\Applicant\NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\Applicant\NotificationController::class, 'unreadCount']);
        Route::post('/notifications/{notification}/mark-read', [\App\Http\Controllers\Api\Applicant\NotificationController::class, 'markAsRead']);
        Route::post('/notifications/mark-all-read', [\App\Http\Controllers\Api\Applicant\NotificationController::class, 'markAllAsRead']);
        Route::delete('/notifications/{notification}', [\App\Http\Controllers\Api\Applicant\NotificationController::class, 'destroy']);

        // Support Tickets
        Route::post('/support/tickets', [\App\Http\Controllers\Api\SupportController::class, 'store']);
        Route::get('/support/tickets', [\App\Http\Controllers\Api\SupportController::class, 'myTickets']);
        Route::get('/support/tickets/{ticket}', [\App\Http\Controllers\Api\SupportController::class, 'showMine']);
        Route::post('/support/tickets/{ticket}/reply', [\App\Http\Controllers\Api\SupportController::class, 'replyMine']);
    });

    /*
    |----------------------------------------------------------------------
    | GIS Officer Routes
    |----------------------------------------------------------------------
    */
    Route::middleware([\App\Http\Middleware\EnsureRole::class . ':gis_officer,gis_admin,admin'])->prefix('gis')->group(function () {

        // Queue management
        Route::get('/metrics', [CaseQueueController::class, 'metrics']);
        Route::get('/cases', [CaseQueueController::class, 'index']);
        Route::get('/cases/{application}', [CaseQueueController::class, 'show']);
        Route::post('/cases/{application}/assign', [CaseQueueController::class, 'assignToSelf']);
        Route::get('/batch/stats', [CaseQueueController::class, 'batchStats']);

        // Utilities
        Route::get('/reason-codes', [CaseUtilityController::class, 'reasonCodes']);
        Route::get('/cases/{application}/documents/{document}/download', [CaseUtilityController::class, 'downloadDocument']);

        // Review actions
        Route::post('/cases/{application}/escalate', [CaseReviewController::class, 'escalate']);
        Route::post('/cases/{application}/notes', [CaseReviewController::class, 'addNote']);
        Route::post('/cases/{application}/request-info', [CaseReviewController::class, 'requestInfo']);
        Route::post('/cases/{application}/documents/{document}/verify', [CaseReviewController::class, 'verifyDocument']);

        // Decision making (two-step approval workflow)
        Route::post('/cases/{application}/submit-for-approval', [CaseDecisionController::class, 'submitForApproval']);
        Route::post('/cases/{application}/approve', [CaseDecisionController::class, 'approve']);
        Route::post('/cases/{application}/deny', [CaseDecisionController::class, 'deny']);
        Route::post('/cases/{application}/issue', [CaseDecisionController::class, 'issueVisa']);
        Route::post('/cases/{application}/revert', [CaseDecisionController::class, 'revertDecision']);

        // Batch operations
        Route::post('/batch/assign', [CaseBatchController::class, 'batchAssign']);
        Route::post('/batch/status', [CaseBatchController::class, 'batchUpdateStatus']);
        Route::post('/batch/approve', [CaseBatchController::class, 'batchApprove']);
        Route::post('/batch/request-info', [CaseBatchController::class, 'batchRequestInfo']);

        // Support Tickets for Officers
        Route::get('/support/tickets', [\App\Http\Controllers\Api\SupportController::class, 'index']);
        Route::get('/support/tickets/unread-count', [\App\Http\Controllers\Api\SupportController::class, 'unreadCount']);
        Route::get('/support/tickets/{ticket}', [\App\Http\Controllers\Api\SupportController::class, 'show']);
        Route::post('/support/tickets/{ticket}/reply', [\App\Http\Controllers\Api\SupportController::class, 'reply']);
        Route::put('/support/tickets/{ticket}/status', [\App\Http\Controllers\Api\SupportController::class, 'updateStatus']);
        Route::post('/support/tickets/{ticket}/override-approve', [\App\Http\Controllers\Api\SupportController::class, 'overrideToApproved']);
    });

    /*
    |----------------------------------------------------------------------
    | MFA Reviewer Routes
    |----------------------------------------------------------------------
    */
    Route::middleware([\App\Http\Middleware\EnsureRole::class . ':mfa_reviewer,admin'])->prefix('mfa')->group(function () {

        Route::get('/metrics', [EscalationController::class, 'metrics']);
        Route::get('/missions', [EscalationController::class, 'missions']);
        Route::get('/reason-codes', [EscalationController::class, 'reasonCodes']);
        Route::get('/escalations', [EscalationController::class, 'index']);
        Route::get('/escalations/{application}', [EscalationController::class, 'show']);
        Route::post('/escalations/{application}/assign', [EscalationController::class, 'assignToSelf']);
        Route::post('/escalations/{application}/notes', [EscalationController::class, 'addNote']);
        Route::put('/escalations/{application}/notes/{note}', [EscalationController::class, 'updateNote']);
        Route::post('/escalations/{application}/request-info', [EscalationController::class, 'requestInfo']);
        Route::get('/escalations/{application}/documents/{document}/download', [EscalationController::class, 'downloadDocument']);
        Route::post('/escalations/{application}/submit-for-approval', [EscalationController::class, 'submitForApproval']);
        Route::post('/escalations/{application}/approve', [EscalationController::class, 'approve']);
        Route::post('/escalations/{application}/deny', [EscalationController::class, 'deny']);
        Route::post('/escalations/{application}/issue', [EscalationController::class, 'issueVisa']);
        Route::post('/escalations/{application}/return', [EscalationController::class, 'returnToGis']);
        Route::post('/escalations/{application}/revert', [EscalationController::class, 'revertDecision']);
    });

    /*
    |----------------------------------------------------------------------
    | Admin Routes
    |----------------------------------------------------------------------
    */
    Route::middleware([\App\Http\Middleware\EnsureRole::class . ':admin'])->prefix('admin')->group(function () {

        // Users
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::post('/users/{user}/deactivate', [UserController::class, 'deactivate']);
        Route::post('/users/{user}/activate', [UserController::class, 'activate']);
        Route::post('/users/{user}/unlock-account', [UserController::class, 'unlockAccount'])
            ->middleware(\App\Http\Middleware\EnsureRole::class . ':admin,super_admin');

        // Applications & Payments
        Route::get('/applications', [ReportController::class, 'applications']);
        Route::get('/applications/{application}', [ReportController::class, 'showApplication']);
        Route::get('/applications/{application}/documents/{document}/download', [ReportController::class, 'downloadDocument']);
        Route::get('/payments', [ReportController::class, 'payments']);

        // Tier configuration
        Route::get('/tier-rules', [TierConfigController::class, 'index']);
        Route::post('/tier-rules', [TierConfigController::class, 'store']);
        Route::put('/tier-rules/{tierRule}', [TierConfigController::class, 'update']);
        Route::delete('/tier-rules/{tierRule}', [TierConfigController::class, 'destroy']);

        // Visa Types management
        Route::get('/visa-types', [\App\Http\Controllers\Api\Admin\VisaTypeController::class, 'index']);
        Route::post('/visa-types', [\App\Http\Controllers\Api\Admin\VisaTypeController::class, 'store']);
        Route::get('/visa-types/{visaType}', [\App\Http\Controllers\Api\Admin\VisaTypeController::class, 'show']);
        Route::put('/visa-types/{visaType}', [\App\Http\Controllers\Api\Admin\VisaTypeController::class, 'update']);
        Route::delete('/visa-types/{visaType}', [\App\Http\Controllers\Api\Admin\VisaTypeController::class, 'destroy']);

        // Service Tiers management
        Route::get('/service-tiers', [ServiceTierController::class, 'index']);
        Route::post('/service-tiers', [ServiceTierController::class, 'store']);
        Route::get('/service-tiers/{serviceTier}', [ServiceTierController::class, 'show']);
        Route::put('/service-tiers/{serviceTier}', [ServiceTierController::class, 'update']);
        Route::delete('/service-tiers/{serviceTier}', [ServiceTierController::class, 'destroy']);

        // Reason Codes management
        Route::get('/reason-codes', [ReasonCodeController::class, 'index']);
        Route::post('/reason-codes', [ReasonCodeController::class, 'store']);
        Route::get('/reason-codes/{reasonCode}', [ReasonCodeController::class, 'show']);
        Route::put('/reason-codes/{reasonCode}', [ReasonCodeController::class, 'update']);
        Route::delete('/reason-codes/{reasonCode}', [ReasonCodeController::class, 'destroy']);

        // SLA Monitoring
        Route::get('/sla/dashboard', [\App\Http\Controllers\Api\Admin\SlaController::class, 'dashboard']);
        Route::get('/sla/at-risk', [\App\Http\Controllers\Api\Admin\SlaController::class, 'atRisk']);
        Route::get('/sla/breached', [\App\Http\Controllers\Api\Admin\SlaController::class, 'breached']);
        Route::get('/sla/history', [\App\Http\Controllers\Api\Admin\SlaController::class, 'history']);
        Route::post('/sla/send-warnings', [\App\Http\Controllers\Api\Admin\SlaController::class, 'sendWarnings']);

        // Reports
        Route::get('/reports/overview', [ReportController::class, 'overview']);
        Route::get('/reports/volume', [ReportController::class, 'applicationVolume']);
        Route::get('/reports/payments', [ReportController::class, 'paymentReport']);
        Route::get('/reports/sla', [ReportController::class, 'slaReport']);
        Route::get('/reports/audit-logs', [ReportController::class, 'auditLogs']);

        // Analytics
        Route::get('/analytics/dashboard', [\App\Http\Controllers\Api\Admin\AnalyticsController::class, 'dashboard']);
        Route::get('/analytics/officers', [\App\Http\Controllers\Api\Admin\AnalyticsController::class, 'officerPerformance']);
        Route::get('/analytics/export', [\App\Http\Controllers\Api\Admin\AnalyticsController::class, 'exportCsv']);
        
        // Financial Analytics
        Route::get('/analytics/revenue', [\App\Http\Controllers\Api\Admin\AnalyticsController::class, 'getRevenue']);
        Route::get('/analytics/revenue/by-visa-type', [\App\Http\Controllers\Api\Admin\AnalyticsController::class, 'getRevenueByVisaType']);
        Route::get('/analytics/revenue/by-country', [\App\Http\Controllers\Api\Admin\AnalyticsController::class, 'getRevenueByCountry']);
        Route::get('/analytics/revenue/by-tier', [\App\Http\Controllers\Api\Admin\AnalyticsController::class, 'getRevenueByTier']);
        Route::get('/analytics/revenue/trends', [\App\Http\Controllers\Api\Admin\AnalyticsController::class, 'getRevenueTrends']);
        
        // Visitor Analytics
        Route::get('/analytics/visitors/by-country', [\App\Http\Controllers\Api\Admin\AnalyticsController::class, 'getVisitorsByCountry']);
        Route::get('/analytics/visitors/approval-rates', [\App\Http\Controllers\Api\Admin\AnalyticsController::class, 'getApprovalRates']);
        Route::get('/analytics/visitors/trends', [\App\Http\Controllers\Api\Admin\AnalyticsController::class, 'getVisitorTrends']);
        Route::get('/analytics/visitors/demographics', [\App\Http\Controllers\Api\Admin\AnalyticsController::class, 'getDemographics']);
        
        // Export
        Route::post('/analytics/export/excel', [\App\Http\Controllers\Api\Admin\ExportController::class, 'exportExcel']);
        Route::post('/analytics/export/csv', [\App\Http\Controllers\Api\Admin\ExportController::class, 'exportCsv']);
        
        // AI Assistant
        Route::post('/ai-assistant/query', [\App\Http\Controllers\Api\Admin\AIAssistantController::class, 'query']);
        Route::post('/ai-assistant/clear-context', [\App\Http\Controllers\Api\Admin\AIAssistantController::class, 'clearContext']);
        Route::get('/ai-assistant/suggestions', [\App\Http\Controllers\Api\Admin\AIAssistantController::class, 'getSuggestions']);
        Route::post('/analytics/export/csv', [\App\Http\Controllers\Api\Admin\ExportController::class, 'exportCsv']);
        
        // AI Assistant
        Route::post('/ai-assistant/query', [\App\Http\Controllers\Api\Admin\AIAssistantController::class, 'query']);
        Route::post('/ai-assistant/clear-context', [\App\Http\Controllers\Api\Admin\AIAssistantController::class, 'clearContext']);
        Route::get('/ai-assistant/suggestions', [\App\Http\Controllers\Api\Admin\AIAssistantController::class, 'getSuggestions']);

        // Payment Management - HARDENED SECURITY
        Route::prefix('payments')->middleware(['payment.rate.limit'])->group(function () {
            // Payment statistics (admin only, standard throttling)
            Route::get('/statistics', [\App\Http\Controllers\Api\PaymentController::class, 'statistics'])
                ->middleware('throttle:60,1');
            
            // Bank transfer confirmation (admin only, medium throttling)
            Route::post('/confirm-bank-transfer', [\App\Http\Controllers\Api\PaymentController::class, 'confirmBankTransfer'])
                ->middleware('throttle:30,1');
        });

        // Refund Management - HARDENED SECURITY (finance_officer and admin only)
        Route::prefix('refunds')->middleware(['role:finance_officer,admin', 'payment.rate.limit'])->group(function () {
            // Refund listing (standard throttling)
            Route::get('/', [\App\Http\Controllers\Api\RefundController::class, 'index'])
                ->middleware('throttle:60,1');
            
            // Refund initiation (strict throttling - 10 per hour)
            Route::post('/', [\App\Http\Controllers\Api\RefundController::class, 'initiate'])
                ->middleware('throttle:10,60');
            
            // Refund details (standard throttling)
            Route::get('/{refundRequest}', [\App\Http\Controllers\Api\RefundController::class, 'show'])
                ->middleware('throttle:60,1');
            
            // Refund approval (medium throttling)
            Route::post('/{refundRequest}/approve', [\App\Http\Controllers\Api\RefundController::class, 'approve'])
                ->middleware('throttle:30,1');
            
            // Refund rejection (medium throttling)
            Route::post('/{refundRequest}/reject', [\App\Http\Controllers\Api\RefundController::class, 'reject'])
                ->middleware('throttle:30,1');
        });

        // Routing Management
        Route::get('/routing/applications/{application}', [\App\Http\Controllers\Api\Admin\RoutingController::class, 'getRouting']);
        Route::post('/routing/applications/{application}/route', [\App\Http\Controllers\Api\Admin\RoutingController::class, 'routeApplication']);
        Route::post('/routing/applications/{application}/reroute', [\App\Http\Controllers\Api\Admin\RoutingController::class, 'reRouteApplication']);
        Route::get('/routing/statistics', [\App\Http\Controllers\Api\Admin\RoutingController::class, 'statistics']);
        Route::post('/routing/test', [\App\Http\Controllers\Api\Admin\RoutingController::class, 'testRouting']);
    });
});
