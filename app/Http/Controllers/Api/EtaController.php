<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EtaApplication;
use App\Models\VisaType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class EtaController extends Controller
{
    /**
     * Check eligibility for ETA vs Visa based on nationality.
     * This is the entry point that determines application flow.
     */
    public function checkEligibility(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nationality' => 'required|string|size:2',
        ]);

        $nationality = strtoupper($validated['nationality']);

        // Use ETA Eligibility Service for comprehensive check
        $eligibilityService = app(\App\Services\EtaEligibilityService::class);
        $authType = $eligibilityService->getAuthorizationType($nationality);

        return response()->json([
            'nationality' => $nationality,
            'authorization_required' => $authType['authorization'],
            'application_flow' => $authType['authorization'] === 'eta' ? 'eta_application' : 'visa_application',
            'details' => $authType,
            'next_step' => $authType['authorization'] === 'eta' 
                ? 'Proceed to ETA application form' 
                : 'Proceed to Visa application form',
        ]);
    }

    /**
     * Get issuing authorities for a specific nationality.
     */
    public function getIssuingAuthorities(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nationality' => 'required|string|size:2',
        ]);

        $nationality = strtoupper($validated['nationality']);
        
        $passportService = app(\App\Services\PassportVerificationService::class);
        $authorities = $passportService->getIssuingAuthorities($nationality);

        return response()->json([
            'nationality' => $nationality,
            'issuing_authorities' => $authorities,
            'real_time_verification_available' => $passportService->isRealtimeVerificationAvailable($nationality),
        ]);
    }

    /**
     * Validate passport number format in real-time (as user types).
     */
    public function validatePassportNumber(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'passport_number' => 'required|string|max:50',
            'nationality' => 'required|string|size:2',
        ]);

        $passportService = app(\App\Services\PassportVerificationService::class);
        
        // Perform format validation only (quick check)
        $formatValidation = $passportService->verifyPassport([
            'passport_number' => $validated['passport_number'],
            'nationality' => $validated['nationality'],
            'issue_date' => '2020-01-01', // Dummy date for format check
            'expiry_date' => '2030-01-01', // Dummy date for format check
            'issuing_authority' => 'Passport Office', // Dummy authority
        ]);

        // Extract only format-related errors
        $formatErrors = array_filter($formatValidation['errors'] ?? [], function($error) {
            return strpos($error, 'format') !== false || strpos($error, 'Invalid passport') !== false;
        });

        $isValidFormat = empty($formatErrors);

        return response()->json([
            'valid_format' => $isValidFormat,
            'passport_number' => $validated['passport_number'],
            'nationality' => $validated['nationality'],
            'message' => $isValidFormat 
                ? 'Passport number format is valid' 
                : 'Invalid passport number format',
            'errors' => $formatErrors,
            'sumsub_verification_recommended' => config('sumsub.enabled', false),
        ]);
    }

    /**
     * Get eligible ETA types for a nationality.
     */
    public function eligibleTypes(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nationality' => 'required|string|size:2',
        ]);

        $nationality = strtoupper($validated['nationality']);

        // Use ETA Eligibility Service for comprehensive check
        $eligibilityService = app(\App\Services\EtaEligibilityService::class);
        $authType = $eligibilityService->getAuthorizationType($nationality);

        // FIX: Use database query instead of loading all and filtering in PHP
        // Note: This assumes eligible_nationalities is stored as JSON array
        $etaTypes = VisaType::where('category', 'eta')
            ->where('is_active', true)
            ->whereRaw('JSON_CONTAINS(eligible_nationalities, ?)', [json_encode($nationality)])
            ->get();

        // Check if Sumsub verification is available
        $sumsubEnabled = config('sumsub.enabled', false) && config('sumsub.eta_enabled', false);

        return response()->json([
            'nationality' => $nationality,
            'authorization_type' => $authType,
            'eta_types' => $etaTypes->values(),
            'is_eligible' => $etaTypes->isNotEmpty(),
            'routing_logic' => $eligibilityService->getRoutingLogic(),
            'sumsub_verification_available' => $sumsubEnabled,
            'passport_verification_method' => $sumsubEnabled ? 'sumsub' : 'offline',
        ]);
    }

    /**
     * Submit a new ETA application.
     * 
     * Per specification:
     * - ETA is FREE (no payment required)
     * - TAID is generated before ETA creation
     * - Duplicate prevention check (passport + nationality)
     * - Basic screening checks performed
     * - Auto-issue if no flags, otherwise mark as 'flagged'
     */
    public function apply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'visa_type_id' => 'required|exists:visa_types,id',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'date_of_birth' => 'required|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'nationality' => 'required|string|size:2',
            'passport_number' => 'required|string|max:50',
            'passport_issue_date' => 'required|date|before:today',
            'passport_expiry_date' => 'required|date|after:today',
            'issuing_authority' => 'required|string|max:200',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:20',
            'residential_address' => 'nullable|string|max:500',
            'intended_arrival_date' => 'required|date|after:today',
            'port_of_entry' => 'nullable|string|max:100',
            'airline' => 'nullable|string|max:100',
            'flight_number' => 'nullable|string|max:20',
            'address_in_ghana' => 'nullable|string|max:500',
            'host_name' => 'nullable|string|max:100',
            'host_phone' => 'nullable|string|max:20',
            'denied_entry_before' => 'boolean',
            'criminal_conviction' => 'boolean',
            'previous_ghana_visa' => 'boolean',
            'travel_history' => 'nullable|string|max:500',
            'passport_scan' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',
            'hotel_booking' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // PASSPORT VERIFICATION: Verify passport before processing
        $passportService = app(\App\Services\PassportVerificationService::class);
        $passportVerification = $passportService->verifyPassport([
            'passport_number' => $validated['passport_number'],
            'nationality' => $validated['nationality'],
            'issue_date' => $validated['passport_issue_date'],
            'expiry_date' => $validated['passport_expiry_date'],
            'issuing_authority' => $validated['issuing_authority'],
        ]);

        // Check for passport verification errors (expired passport blocks application)
        if (!empty($passportVerification['errors'])) {
            return response()->json([
                'message' => 'Passport verification failed',
                'errors' => $passportVerification['errors'],
                'passport_status' => $passportVerification['status'],
            ], 422);
        }

        // Store warnings for later display (e.g., <6 months expiry)
        $passportWarnings = $passportVerification['warnings'] ?? [];

        // Verify visa type is ETA
        $visaType = VisaType::findOrFail($validated['visa_type_id']);
        if ($visaType->category !== 'eta') {
            return response()->json(['message' => 'Invalid ETA type'], 422);
        }

        // Check nationality eligibility
        if (!empty($visaType->eligible_nationalities)) {
            $nationality = strtoupper($validated['nationality']);
            if (!in_array($nationality, $visaType->eligible_nationalities)) {
                return response()->json([
                    'message' => 'Your nationality is not eligible for this ETA type',
                ], 422);
            }
        }

        // DUPLICATE PREVENTION: Check for existing valid ETA
        $screeningService = app(\App\Services\EtaScreeningService::class);
        $existingEta = $screeningService->checkDuplicateEta(
            $validated['passport_number'],
            $validated['nationality']
        );
        
        if ($existingEta) {
            return response()->json([
                'message' => 'You already have an active ETA valid until ' . 
                           $existingEta->valid_until->format('F j, Y'),
                'eta_number' => $existingEta->eta_number,
                'valid_until' => $existingEta->valid_until->format('Y-m-d'),
                'reference_number' => $existingEta->reference_number,
            ], 409);
        }

        // TAID GENERATION: Create TAID before ETA application
        $travelAuth = \App\Models\TravelAuthorization::createTaid(
            $validated['passport_number'],
            $validated['nationality'],
            'ETA'
        );

        // Create ETA application with encrypted PII
        $eta = EtaApplication::create([
            'reference_number' => EtaApplication::generateReferenceNumber(),
            'taid' => $travelAuth->taid,
            'user_id' => $request->user()?->id,
            'first_name_encrypted' => Crypt::encryptString($validated['first_name']),
            'last_name_encrypted' => Crypt::encryptString($validated['last_name']),
            'date_of_birth' => $validated['date_of_birth'],
            'gender' => $validated['gender'] ?? null,
            'nationality_encrypted' => Crypt::encryptString($validated['nationality']),
            'passport_number_encrypted' => Crypt::encryptString($validated['passport_number']),
            'passport_issue_date' => $validated['passport_issue_date'],
            'passport_expiry_date' => $validated['passport_expiry_date'],
            'issuing_authority' => $validated['issuing_authority'],
            'email_encrypted' => Crypt::encryptString($validated['email']),
            'phone_encrypted' => isset($validated['phone']) ? Crypt::encryptString($validated['phone']) : null,
            'residential_address_encrypted' => isset($validated['residential_address']) ? Crypt::encryptString($validated['residential_address']) : null,
            'intended_arrival_date' => $validated['intended_arrival_date'],
            'port_of_entry' => $validated['port_of_entry'] ?? null,
            'airline' => $validated['airline'] ?? null,
            'flight_number' => $validated['flight_number'] ?? null,
            'address_in_ghana_encrypted' => isset($validated['address_in_ghana']) ? Crypt::encryptString($validated['address_in_ghana']) : null,
            'host_name' => $validated['host_name'] ?? null,
            'host_phone' => $validated['host_phone'] ?? null,
            'denied_entry_before' => $validated['denied_entry_before'] ?? false,
            'criminal_conviction' => $validated['criminal_conviction'] ?? false,
            'previous_ghana_visa' => $validated['previous_ghana_visa'] ?? false,
            'travel_history' => $validated['travel_history'] ?? null,
            'passport_scan_path' => isset($validated['passport_scan']) ? $request->file('passport_scan')->store('eta-documents', 'private') : null,
            'photo_path' => isset($validated['photo']) ? $request->file('photo')->store('eta-photos', 'private') : null,
            'hotel_booking_path' => isset($validated['hotel_booking']) ? $request->file('hotel_booking')->store('eta-documents', 'private') : null,
            'fee_amount' => 0, // ETA is FREE per specification
            'validity_days' => 90, // Fixed 90 days per specification
            'entry_type' => 'single', // ETA is always single entry
            'status' => 'pending_screening',
            'payment_status' => 'not_required', // ETA is free
            'passport_verification_status' => $passportVerification['status'],
            'passport_verification_data' => json_encode($passportVerification),
        ]);

        // SCREENING: Perform basic screening checks
        $screeningService->autoIssueOrFlag($eta);

        return response()->json([
            'message' => 'ETA application submitted successfully',
            'reference_number' => $eta->reference_number,
            'taid' => $travelAuth->taid,
            'status' => $eta->fresh()->status,
            'eta_number' => $eta->fresh()->eta_number,
            'fee_amount' => 0,
            'passport_warnings' => $passportWarnings,
            'passport_verification_status' => $passportVerification['status'],
            'eta' => $this->formatEtaResponse($eta->fresh()),
        ], 201);
    }

    /**
     * Check ETA application status.
     */
    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference_number' => 'required|string',
            'passport_number' => 'required|string',
        ]);

        $eta = EtaApplication::where('reference_number', $validated['reference_number'])->first();

        if (!$eta) {
            return response()->json(['message' => 'ETA application not found'], 404);
        }

        // Verify passport number matches
        $storedPassport = Crypt::decryptString($eta->passport_number_encrypted);
        if (strtoupper($storedPassport) !== strtoupper($validated['passport_number'])) {
            return response()->json(['message' => 'Invalid credentials'], 403);
        }

        return response()->json([
            'eta' => $this->formatEtaResponse($eta),
        ]);
    }

    /**
     * Verify ETA at border (for immigration officers).
     * Per specification: ETA must be 'issued' status (not 'approved')
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eta_number' => 'nullable|string',
            'reference_number' => 'nullable|string',
            'passport_number' => 'required|string',
        ]);

        if (empty($validated['eta_number']) && empty($validated['reference_number'])) {
            return response()->json(['message' => 'Either ETA number or reference number is required'], 422);
        }

        $query = EtaApplication::query();

        if (!empty($validated['eta_number'])) {
            $query->where('eta_number', $validated['eta_number']);
        } else {
            $query->where('reference_number', $validated['reference_number']);
        }

        $eta = $query->first();

        if (!$eta) {
            return response()->json([
                'valid' => false,
                'message' => 'ETA not found',
            ], 404);
        }

        // Verify passport number
        $storedPassport = Crypt::decryptString($eta->passport_number_encrypted);
        if (strtoupper($storedPassport) !== strtoupper($validated['passport_number'])) {
            return response()->json([
                'valid' => false,
                'message' => 'Passport number does not match',
            ], 403);
        }

        // Check if issued and not expired (per spec: status must be 'issued')
        $isValid = $eta->status === 'issued' && !$eta->isExpired();

        return response()->json([
            'valid' => $isValid,
            'status' => $eta->status,
            'eta_number' => $eta->eta_number,
            'holder_name' => Crypt::decryptString($eta->first_name_encrypted) . ' ' . Crypt::decryptString($eta->last_name_encrypted),
            'nationality' => Crypt::decryptString($eta->nationality_encrypted),
            'valid_until' => $eta->valid_until?->format('Y-m-d') ?? $eta->expires_at?->format('Y-m-d'),
            'entry_type' => $eta->entry_type,
            'message' => $isValid ? 'ETA is valid for entry' : ($eta->isExpired() ? 'ETA has expired' : 'ETA is not issued'),
        ]);
    }

    /**
     * Process ETA payment callback.
     */
    public function paymentCallback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference_number' => 'required|string',
            'payment_reference' => 'required|string',
            'status' => 'required|in:success,failed',
        ]);

        $eta = EtaApplication::where('reference_number', $validated['reference_number'])->first();

        if (!$eta) {
            return response()->json(['message' => 'ETA application not found'], 404);
        }

        if ($validated['status'] === 'success') {
            $eta->update([
                'payment_status' => 'completed',
                'payment_reference' => $validated['payment_reference'],
            ]);

            // Auto-approve for ECOWAS (simple check - no security flags)
            if (!$eta->denied_entry_before && !$eta->criminal_conviction) {
                $this->approveEta($eta);
            }
        } else {
            $eta->update([
                'payment_status' => 'failed',
            ]);
        }

        return response()->json([
            'message' => $validated['status'] === 'success' ? 'Payment processed successfully' : 'Payment failed',
            'eta' => $this->formatEtaResponse($eta->fresh()),
        ]);
    }

    /**
     * Approve an ETA and generate ETA number with QR code.
     */
    private function approveEta(EtaApplication $eta): void
    {
        $eta->update([
            'status' => 'approved',
            'approved_at' => now(),
            'expires_at' => now()->addDays($eta->validity_days),
        ]);

        $eta->generateEtaNumber();

        // Generate QR code
        $qrService = app(\App\Services\QrCodeService::class);
        $qrData = $qrService->generateEtaQrData($eta);
        $eta->update(['qr_code' => $qrData]);

        // Send confirmation notification
        try {
            app(\App\Services\NotificationService::class)->sendNotification(
                null,
                'email',
                'eta-approved',
                Crypt::decryptString($eta->email_encrypted),
                'Your Ghana ETA Has Been Approved',
                [
                    'first_name' => Crypt::decryptString($eta->first_name_encrypted),
                    'eta_number' => $eta->eta_number,
                    'valid_until' => $eta->expires_at->format('F j, Y'),
                    'entry_type' => $eta->entry_type,
                ]
            );
        } catch (\Exception $e) {
            \Log::error('Failed to send ETA approval notification: ' . $e->getMessage());
        }
    }

    /**
     * Format ETA response (decrypt sensitive fields).
     */
    private function formatEtaResponse(EtaApplication $eta): array
    {
        return [
            'reference_number' => $eta->reference_number,
            'eta_number' => $eta->eta_number,
            'status' => $eta->status,
            'first_name' => Crypt::decryptString($eta->first_name_encrypted),
            'last_name' => Crypt::decryptString($eta->last_name_encrypted),
            'nationality' => Crypt::decryptString($eta->nationality_encrypted),
            'passport_number' => substr(Crypt::decryptString($eta->passport_number_encrypted), 0, 3) . '****',
            'date_of_birth' => $eta->date_of_birth->format('Y-m-d'),
            'intended_arrival_date' => $eta->intended_arrival_date->format('Y-m-d'),
            'port_of_entry' => $eta->port_of_entry,
            'validity_days' => $eta->validity_days,
            'entry_type' => $eta->entry_type,
            'fee_amount' => $eta->fee_amount,
            'payment_status' => $eta->payment_status,
            'approved_at' => $eta->approved_at?->format('Y-m-d H:i:s'),
            'expires_at' => $eta->expires_at?->format('Y-m-d'),
            'created_at' => $eta->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
