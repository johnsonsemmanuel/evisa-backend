<?php

namespace App\Services;

use App\Models\EtaApplication;
use App\Models\Application;
use App\Models\BoardingAuthorization;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class BorderVerificationService
{
    /**
     * Verify travel authorization for a passport and nationality.
     */
    public function verifyAuthorization(
        string $passportNumber,
        string $nationality,
        ?string $etaNumber = null,
        ?string $visaId = null
    ): array {
        $passportNumber = strtoupper(trim($passportNumber));
        $nationality = strtoupper(trim($nationality));

        // Log verification attempt
        $this->createAuditLog('verification_attempt', [
            'passport_masked' => $this->maskPassportPlain($passportNumber),
            'nationality' => $nationality,
            'eta_number' => $etaNumber,
            'visa_id' => $visaId,
        ]);

        // Check visa first (higher priority)
        $visa = $this->checkVisa($passportNumber, $nationality, $visaId);
        if ($visa) {
            $response = $this->buildAuthorizedResponse('VISA', $visa);
            $this->createAuditLog('verification_success', [
                'type' => 'VISA',
                'visa_id' => $visa->reference_number,
            ]);
            return $response;
        }

        // Check ETA
        $eta = $this->checkEta($passportNumber, $nationality, $etaNumber);
        if ($eta) {
            $response = $this->buildAuthorizedResponse('ETA', $eta);
            $this->createAuditLog('verification_success', [
                'type' => 'ETA',
                'eta_number' => $eta->eta_number,
            ]);
            return $response;
        }

        // Determine requirement based on nationality
        $response = $this->buildDeniedResponse($nationality);
        $this->createAuditLog('verification_denied', [
            'status' => $response['status'],
            'nationality' => $nationality,
        ]);
        
        return $response;
    }

    /**
     * Check for valid ETA.
     */
    protected function checkEta(
        string $passportNumber,
        string $nationality,
        ?string $etaNumber = null
    ): ?EtaApplication {
        $query = EtaApplication::where('status', 'issued')
            ->where('expires_at', '>', now());

        if ($etaNumber) {
            $query->where('eta_number', $etaNumber);
        }

        $etas = $query->get();

        foreach ($etas as $eta) {
            try {
                $storedPassport = Crypt::decryptString($eta->passport_number_encrypted);
                $storedNationality = Crypt::decryptString($eta->nationality_encrypted);

                if (strtoupper($storedPassport) === $passportNumber && 
                    strtoupper($storedNationality) === $nationality) {
                    return $eta;
                }
            } catch (\Exception $e) {
                Log::error('Failed to decrypt ETA data', [
                    'eta_id' => $eta->id,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        return null;
    }

    /**
     * Check for valid visa.
     */
    protected function checkVisa(
        string $passportNumber,
        string $nationality,
        ?string $visaId = null
    ): ?Application {
        $query = Application::where('status', 'issued')
            ->where(function($q) {
                $q->whereNull('visa_expiry')
                  ->orWhere('visa_expiry', '>', now());
            });

        if ($visaId) {
            $query->where('reference_number', $visaId);
        }

        $visas = $query->get();

        foreach ($visas as $visa) {
            try {
                $storedPassport = Crypt::decryptString($visa->passport_number_encrypted);
                $storedNationality = Crypt::decryptString($visa->nationality_encrypted);

                if (strtoupper($storedPassport) === $passportNumber && 
                    strtoupper($storedNationality) === $nationality) {
                    return $visa;
                }
            } catch (\Exception $e) {
                Log::error('Failed to decrypt visa data', [
                    'application_id' => $visa->id,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        return null;
    }

    /**
     * Generate Boarding Authorization Code.
     */
    public function generateBac(array $authorizationData, int $userId): BoardingAuthorization
    {
        $bac = BoardingAuthorization::create([
            'authorization_code' => BoardingAuthorization::generateCode(),
            'passport_number' => Crypt::encryptString($authorizationData['passport_number']),
            'nationality' => $authorizationData['nationality'],
            'authorization_type' => $authorizationData['type'],
            'eta_number' => $authorizationData['eta_number'] ?? null,
            'visa_id' => $authorizationData['visa_id'] ?? null,
            'verification_timestamp' => now(),
            'expiry_timestamp' => now()->addHours(config('border.bac_expiry_hours', 24)),
            'verified_by_user_id' => $userId,
        ]);

        $this->createAuditLog('bac_generated', [
            'authorization_code' => $bac->authorization_code,
            'authorization_type' => $bac->authorization_type,
            'user_id' => $userId,
        ]);

        return $bac;
    }

    /**
     * Mark authorization as used.
     */
    public function markAsUsed(
        EtaApplication|Application $authorization,
        string $portOfEntry,
        int $officerId
    ): void {
        if ($authorization instanceof EtaApplication) {
            $authorization->update([
                'status' => 'used',
                'entry_date' => now(),
                'port_of_entry_actual' => $portOfEntry,
                'entry_officer_id' => $officerId,
            ]);

            $this->createAuditLog('eta_entry_confirmed', [
                'eta_number' => $authorization->eta_number,
                'port_of_entry' => $portOfEntry,
                'officer_id' => $officerId,
            ]);
        } else {
            // Handle visa entry tracking
            $authorization->update([
                'last_entry_date' => now(),
                'last_port_of_entry' => $portOfEntry,
                'last_entry_officer_id' => $officerId,
            ]);

            $this->createAuditLog('visa_entry_confirmed', [
                'visa_id' => $authorization->reference_number,
                'port_of_entry' => $portOfEntry,
                'officer_id' => $officerId,
            ]);
        }
    }

    /**
     * Verify a Boarding Authorization Code.
     */
    public function verifyBac(string $code): ?array
    {
        $bac = BoardingAuthorization::where('authorization_code', $code)->first();

        if (!$bac) {
            return null;
        }

        if ($bac->isExpired()) {
            return ['valid' => false, 'reason' => 'expired'];
        }

        if ($bac->isUsed()) {
            return [
                'valid' => false,
                'reason' => 'already_used',
                'used_at' => $bac->used_at->format('Y-m-d H:i:s'),
            ];
        }

        try {
            $passportNumber = Crypt::decryptString($bac->passport_number);
            
            return [
                'valid' => true,
                'authorization_code' => $bac->authorization_code,
                'authorization_type' => $bac->authorization_type,
                'expires_at' => $bac->expiry_timestamp->format('Y-m-d H:i:s'),
                'passport_number' => $this->maskPassportPlain($passportNumber),
                'nationality' => $bac->nationality,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to decrypt BAC passport', [
                'code' => $code,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Consume a BAC, marking it as used. Returns false if already used or invalid.
     */
    public function consumeBac(string $code, int $userId): bool
    {
        $bac = BoardingAuthorization::where('authorization_code', $code)->first();

        if (!$bac || $bac->isExpired() || $bac->isUsed()) {
            return false;
        }

        $bac->used_at = now();
        $bac->used_by_user_id = $userId;
        $bac->save();

        $this->createAuditLog('bac_consumed', [
            'authorization_code' => $code,
            'used_by' => $userId,
        ]);

        return true;
    }

    /**
     * Create audit log entry.
     */
    protected function createAuditLog(string $action, array $data): void
    {
        try {
            $now = now();
            AuditLog::create([
                'action' => $action,
                'user_id' => auth()->id(),
                'data' => json_encode($data),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create audit log', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build authorized response.
     */
    protected function buildAuthorizedResponse(string $type, $authorization): array
    {
        if ($type === 'ETA') {
            return [
                'status' => 'AUTHORIZED',
                'authorization_type' => 'ETA',
                'eta_number' => $authorization->eta_number,
                'taid' => $authorization->taid,
                'valid_until' => $authorization->expires_at->format('Y-m-d'),
                'passport_number' => $this->maskPassport($authorization->passport_number_encrypted),
                'holder_name' => $this->getFullName($authorization),
                'nationality' => Crypt::decryptString($authorization->nationality_encrypted),
                'port_of_entry_intended' => $authorization->port_of_entry ?? null,
            ];
        } else {
            return [
                'status' => 'AUTHORIZED',
                'authorization_type' => 'VISA',
                'visa_id' => $authorization->reference_number,
                'taid' => $authorization->taid,
                'visa_type' => $authorization->visaType->name ?? 'Unknown',
                'valid_until' => $authorization->visa_expiry?->format('Y-m-d'),
                'passport_number' => $this->maskPassport($authorization->passport_number_encrypted),
                'holder_name' => $this->getFullName($authorization),
                'nationality' => Crypt::decryptString($authorization->nationality_encrypted),
            ];
        }
    }

    /**
     * Build denied response based on nationality requirements.
     */
    protected function buildDeniedResponse(string $nationality): array
    {
        try {
            $eligibilityService = app(EtaEligibilityService::class);
            $authType = $eligibilityService->getAuthorizationType($nationality);

            if ($authType === 'ETA') {
                return [
                    'status' => 'ETA_REQUIRED',
                    'message' => 'Valid ETA required but not found',
                    'nationality' => $nationality,
                    'eta_application_url' => config('app.url') . '/eta/apply',
                ];
            }

            if ($authType === 'VISA') {
                return [
                    'status' => 'VISA_REQUIRED',
                    'message' => 'Valid visa required but not found',
                    'nationality' => $nationality,
                ];
            }
        } catch (\Exception $e) {
            Log::error('Failed to check eligibility', [
                'nationality' => $nationality,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'status' => 'DENIED',
            'message' => 'Not eligible to enter',
        ];
    }

    /**
     * Mask passport number for display (from encrypted).
     */
    protected function maskPassport(string $encryptedPassport): string
    {
        try {
            $passport = Crypt::decryptString($encryptedPassport);
            return $this->maskPassportPlain($passport);
        } catch (\Exception $e) {
            return '***';
        }
    }

    /**
     * Mask passport number for display (plain text).
     */
    protected function maskPassportPlain(string $passport): string
    {
        $length = strlen($passport);
        if ($length <= 3) {
            return str_repeat('*', $length);
        }
        return substr($passport, 0, 3) . str_repeat('*', $length - 3);
    }

    /**
     * Get full name from authorization.
     */
    protected function getFullName($authorization): string
    {
        try {
            $firstName = Crypt::decryptString($authorization->first_name_encrypted);
            $lastName = Crypt::decryptString($authorization->last_name_encrypted);
            return trim("{$firstName} {$lastName}");
        } catch (\Exception $e) {
            Log::error('Failed to decrypt name', [
                'id' => $authorization->id,
                'error' => $e->getMessage(),
            ]);
            return 'Unknown';
        }
    }
}
