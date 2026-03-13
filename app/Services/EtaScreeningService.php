<?php

namespace App\Services;

use App\Models\EtaApplication;
use App\Models\Watchlist;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * ETA Screening Service
 * 
 * Implements basic screening checks per specification:
 * - Passport expiry < 6 months
 * - Watchlist proximity
 * - Criminal declaration
 * - Sanctions/denied entry declaration
 * 
 * If any check triggers, ETA status = 'flagged' for admin review
 * Otherwise, ETA is auto-issued
 */
class EtaScreeningService
{
    /**
     * Perform all screening checks on an ETA application
     * 
     * @param EtaApplication $eta
     * @return array List of triggered flags
     */
    public function performScreening(EtaApplication $eta): array
    {
        $flags = [];
        
        // Check 1: Passport verification status
        if ($this->checkPassportVerificationStatus($eta)) {
            $flags[] = 'Passport verification failed or flagged';
        }
        
        // Check 2: Passport expiry < 6 months from arrival
        if ($this->checkPassportExpiry($eta)) {
            $flags[] = 'Passport expires in less than 6 months from arrival date';
        }
        
        // Check 3: Watchlist proximity
        if ($this->checkWatchlist($eta)) {
            $flags[] = 'Watchlist match detected - requires verification';
        }
        
        // Check 4: Criminal conviction declared
        if ($eta->criminal_conviction) {
            $flags[] = 'Criminal conviction declared by applicant';
        }
        
        // Check 5: Previous entry denial
        if ($eta->denied_entry_before) {
            $flags[] = 'Previous entry denial declared by applicant';
        }
        
        return $flags;
    }

    /**
     * Check passport verification status
     */
    protected function checkPassportVerificationStatus(EtaApplication $eta): bool
    {
        if (!$eta->passport_verification_status) {
            return false; // No verification performed
        }
        
        // Flag if verification failed or has warnings
        return in_array($eta->passport_verification_status, ['verification_failed', 'expired']);
    }

    /**
     * Check if passport expires within 6 months of arrival
     */
    protected function checkPassportExpiry(EtaApplication $eta): bool
    {
        if (!$eta->passport_expiry_date || !$eta->intended_arrival_date) {
            return false;
        }
        
        $monthsUntilExpiry = $eta->intended_arrival_date->diffInMonths($eta->passport_expiry_date, false);
        
        return $monthsUntilExpiry < 6;
    }

    /**
     * Check if applicant matches any watchlist entries
     */
    protected function checkWatchlist(EtaApplication $eta): bool
    {
        try {
            $passportNumber = Crypt::decryptString($eta->passport_number_encrypted);
            $firstName = Crypt::decryptString($eta->first_name_encrypted);
            $lastName = Crypt::decryptString($eta->last_name_encrypted);
            
            return Watchlist::where(function($query) use ($passportNumber, $firstName, $lastName) {
                $query->where('passport_number', $passportNumber)
                      ->orWhere(function($q) use ($firstName, $lastName) {
                          $q->where('first_name', 'LIKE', "%{$firstName}%")
                            ->where('last_name', 'LIKE', "%{$lastName}%");
                      });
            })->exists();
        } catch (\Exception $e) {
            Log::error("Watchlist check failed for ETA {$eta->reference_number}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Auto-issue ETA or flag for review based on screening results
     * 
     * @param EtaApplication $eta
     * @return void
     */
    public function autoIssueOrFlag(EtaApplication $eta): void
    {
        $flags = $this->performScreening($eta);
        
        if (empty($flags)) {
            // No flags - auto-issue
            $this->issueEta($eta);
        } else {
            // Has flags - mark as flagged for admin review
            $this->flagEta($eta, $flags);
        }
    }

    /**
     * Issue ETA automatically
     */
    protected function issueEta(EtaApplication $eta): void
    {
        $eta->update([
            'status' => 'issued',
            'approved_at' => now(),
            'valid_from' => now(),
            'valid_until' => now()->addDays(90), // Fixed 90 days per spec
            'expires_at' => now()->addDays(90),
        ]);
        
        // Generate ETA number
        $eta->generateEtaNumber();
        
        // Generate QR code
        $qrService = app(QrCodeService::class);
        $qrData = $qrService->generateEtaQrData($eta);
        $eta->update(['qr_code' => $qrData]);
        
        // Send confirmation email with PDF
        $this->sendEtaConfirmation($eta);
        
        Log::info("ETA auto-issued: {$eta->eta_number}");
    }

    /**
     * Flag ETA for admin review
     */
    protected function flagEta(EtaApplication $eta, array $flags): void
    {
        $eta->update([
            'status' => 'flagged',
            'screening_notes' => json_encode($flags),
        ]);
        
        Log::warning("ETA flagged for review: {$eta->reference_number}", [
            'flags' => $flags,
        ]);
        
        // Notify admins about flagged ETA
        $this->notifyAdminsOfFlaggedEta($eta, $flags);
    }

    /**
     * Send ETA confirmation email with PDF attachment
     */
    protected function sendEtaConfirmation(EtaApplication $eta): void
    {
        try {
            $notificationService = app(NotificationService::class);
            
            $notificationService->sendNotification(
                null,
                'email',
                'eta-issued',
                Crypt::decryptString($eta->email_encrypted),
                'Your Ghana ETA Has Been Issued',
                [
                    'first_name' => Crypt::decryptString($eta->first_name_encrypted),
                    'last_name' => Crypt::decryptString($eta->last_name_encrypted),
                    'eta_number' => $eta->eta_number,
                    'passport_number' => Crypt::decryptString($eta->passport_number_encrypted),
                    'valid_from' => $eta->valid_from->format('F j, Y'),
                    'valid_until' => $eta->valid_until->format('F j, Y'),
                    'entry_type' => 'Single Entry',
                ]
            );
        } catch (\Exception $e) {
            Log::error("Failed to send ETA confirmation for {$eta->eta_number}: " . $e->getMessage());
        }
    }

    /**
     * Notify admins about flagged ETA
     */
    protected function notifyAdminsOfFlaggedEta(EtaApplication $eta, array $flags): void
    {
        try {
            // Get all GIS and MFA admins
            $admins = \App\Models\User::whereIn('role', ['gis_admin', 'mfa_admin'])->get();
            
            foreach ($admins as $admin) {
                app(NotificationService::class)->sendNotification(
                    $admin->id,
                    'email',
                    'eta-flagged',
                    $admin->email,
                    'ETA Application Flagged for Review',
                    [
                        'reference_number' => $eta->reference_number,
                        'applicant_name' => Crypt::decryptString($eta->first_name_encrypted) . ' ' . 
                                          Crypt::decryptString($eta->last_name_encrypted),
                        'flags' => $flags,
                    ]
                );
            }
        } catch (\Exception $e) {
            Log::error("Failed to notify admins of flagged ETA: " . $e->getMessage());
        }
    }

    /**
     * Check for duplicate ETA applications
     * 
     * @param string $passportNumber
     * @param string $nationality
     * @return EtaApplication|null
     */
    public function checkDuplicateEta(string $passportNumber, string $nationality): ?EtaApplication
    {
        $encryptedPassport = Crypt::encryptString($passportNumber);
        $encryptedNationality = Crypt::encryptString(strtoupper($nationality));
        
        return EtaApplication::where('passport_number_encrypted', $encryptedPassport)
            ->where('nationality_encrypted', $encryptedNationality)
            ->whereIn('status', ['issued', 'approved'])
            ->where(function($query) {
                $query->where('expires_at', '>', now())
                      ->orWhere('valid_until', '>', now());
            })
            ->first();
    }
}
