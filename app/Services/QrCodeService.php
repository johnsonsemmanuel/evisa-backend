<?php

namespace App\Services;

use App\Models\Application;
use App\Models\EtaApplication;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class QrCodeService
{
    /**
     * Generate QR code data for an approved eVisa.
     */
    public function generateEvisaQrData(Application $application): string
    {
        if ($application->status !== 'approved') {
            throw new \InvalidArgumentException('Cannot generate QR for non-approved application');
        }

        $payload = [
            'type' => 'GHEVISA',
            'ref' => $application->reference_number,
            'v' => 1, // version
            'issued' => $application->decided_at?->format('Ymd'),
            'validity' => $application->visaType?->max_duration_days ?? 90,
            'entry' => $application->visaType?->entry_type ?? 'single',
            'hash' => $this->generateVerificationHash($application),
        ];

        // Encode as compact JSON for QR
        $qrData = 'GH-EVISA:' . base64_encode(json_encode($payload));

        // Update application with QR code
        $application->update(['evisa_qr_code' => $qrData]);

        return $qrData;
    }

    /**
     * Generate QR code data for an approved ETA.
     * Per specification section 3.10: Format must be ETA_NUMBER|PASSPORT_NUMBER
     */
    public function generateEtaQrData(EtaApplication $eta): string
    {
        if (!in_array($eta->status, ['approved', 'issued'])) {
            throw new \InvalidArgumentException('Cannot generate QR for non-approved/issued ETA');
        }

        // Decrypt passport number for QR code
        $passportNumber = Crypt::decryptString($eta->passport_number_encrypted);
        
        // Spec format: ETA_NUMBER|PASSPORT_NUMBER
        $qrData = "{$eta->eta_number}|{$passportNumber}";
        
        return $qrData;
    }

    /**
     * Verify QR code and extract data.
     */
    public function verifyQrCode(string $qrData): array
    {
        if (str_starts_with($qrData, 'GH-EVISA:')) {
            return $this->verifyEvisaQr($qrData);
        } elseif (str_starts_with($qrData, 'GH-ETA:')) {
            return $this->verifyEtaQr($qrData);
        }

        return [
            'valid' => false,
            'error' => 'Unknown QR code format',
        ];
    }

    /**
     * Verify eVisa QR code.
     */
    protected function verifyEvisaQr(string $qrData): array
    {
        try {
            $encoded = substr($qrData, 9); // Remove 'GH-EVISA:'
            $payload = json_decode(base64_decode($encoded), true);

            if (!$payload || !isset($payload['ref'])) {
                return ['valid' => false, 'error' => 'Invalid QR data'];
            }

            $application = Application::where('reference_number', $payload['ref'])
                ->where('status', 'approved')
                ->first();

            if (!$application) {
                return ['valid' => false, 'error' => 'eVisa not found'];
            }

            // Verify hash
            $expectedHash = $this->generateVerificationHash($application);
            if ($payload['hash'] !== $expectedHash) {
                return ['valid' => false, 'error' => 'QR code tampered or invalid'];
            }

            // Check expiry
            $expiryDate = $application->decided_at?->addDays($payload['validity'] ?? 90);
            if ($expiryDate && $expiryDate < now()) {
                return [
                    'valid' => false,
                    'error' => 'eVisa has expired',
                    'expired_on' => $expiryDate->format('Y-m-d'),
                ];
            }

            return [
                'valid' => true,
                'type' => 'evisa',
                'reference_number' => $application->reference_number,
                'holder_name' => $application->first_name . ' ' . $application->last_name,
                'nationality' => $application->nationality,
                'visa_type' => $application->visaType?->name,
                'entry_type' => $payload['entry'],
                'issued_date' => $application->decided_at?->format('Y-m-d'),
                'valid_until' => $expiryDate?->format('Y-m-d'),
            ];
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => 'Failed to decode QR data'];
        }
    }

    /**
     * Verify ETA QR code.
     * Handles both new format (ETA_NUMBER|PASSPORT_NUMBER) and legacy format for backward compatibility.
     */
    protected function verifyEtaQr(string $qrData): array
    {
        try {
            // NEW FORMAT: ETA_NUMBER|PASSPORT_NUMBER (per spec section 3.10)
            if (strpos($qrData, '|') !== false && !str_starts_with($qrData, 'GH-ETA:')) {
                [$etaNumber, $passportNumber] = explode('|', $qrData, 2);
                
                $eta = EtaApplication::where('eta_number', $etaNumber)->first();
                
                if (!$eta) {
                    return ['valid' => false, 'error' => 'ETA not found'];
                }
                
                // Verify passport
                $storedPassport = Crypt::decryptString($eta->passport_number_encrypted);
                if (strtoupper($storedPassport) !== strtoupper($passportNumber)) {
                    return ['valid' => false, 'error' => 'Passport mismatch'];
                }
                
                // Check status (must be 'issued' per spec)
                if ($eta->status !== 'issued') {
                    return ['valid' => false, 'error' => 'ETA not issued'];
                }
                
                // Check expiry
                $validUntil = $eta->valid_until ?? $eta->expires_at;
                if ($validUntil && $validUntil < now()) {
                    return [
                        'valid' => false,
                        'error' => 'ETA has expired',
                        'expired_on' => $validUntil->format('Y-m-d'),
                    ];
                }
                
                return [
                    'valid' => true,
                    'type' => 'eta',
                    'eta_number' => $eta->eta_number,
                    'reference_number' => $eta->reference_number,
                    'holder_name' => Crypt::decryptString($eta->first_name_encrypted) . ' ' . 
                                   Crypt::decryptString($eta->last_name_encrypted),
                    'nationality' => Crypt::decryptString($eta->nationality_encrypted),
                    'entry_type' => $eta->entry_type,
                    'issued_date' => $eta->approved_at?->format('Y-m-d') ?? $eta->valid_from?->format('Y-m-d'),
                    'valid_until' => $validUntil?->format('Y-m-d'),
                ];
            }
            
            // LEGACY FORMAT: GH-ETA:base64(json) - for backward compatibility
            if (str_starts_with($qrData, 'GH-ETA:')) {
                $encoded = substr($qrData, 7); // Remove 'GH-ETA:'
                $payload = json_decode(base64_decode($encoded), true);

                if (!$payload || !isset($payload['eta'])) {
                    return ['valid' => false, 'error' => 'Invalid QR data'];
                }

                $eta = EtaApplication::where('eta_number', $payload['eta'])
                    ->whereIn('status', ['approved', 'issued'])
                    ->first();

                if (!$eta) {
                    return ['valid' => false, 'error' => 'ETA not found'];
                }

                // Check expiry
                $validUntil = $eta->valid_until ?? $eta->expires_at;
                if ($validUntil && $validUntil < now()) {
                    return [
                        'valid' => false,
                        'error' => 'ETA has expired',
                        'expired_on' => $validUntil->format('Y-m-d'),
                    ];
                }

                return [
                    'valid' => true,
                    'type' => 'eta',
                    'eta_number' => $eta->eta_number,
                    'reference_number' => $eta->reference_number,
                    'holder_name' => Crypt::decryptString($eta->first_name_encrypted) . ' ' . 
                                   Crypt::decryptString($eta->last_name_encrypted),
                    'nationality' => Crypt::decryptString($eta->nationality_encrypted),
                    'entry_type' => $eta->entry_type,
                    'issued_date' => $eta->approved_at?->format('Y-m-d'),
                    'valid_until' => $validUntil?->format('Y-m-d'),
                ];
            }
            
            return ['valid' => false, 'error' => 'Unknown QR format'];
        } catch (\Exception $e) {
            return ['valid' => false, 'error' => 'Failed to decode QR data: ' . $e->getMessage()];
        }
    }

    /**
     * Generate verification hash for eVisa.
     */
    protected function generateVerificationHash(Application $application): string
    {
        $data = $application->reference_number . 
                $application->passport_number . 
                $application->decided_at?->format('Ymd') .
                config('app.key');

        return substr(hash('sha256', $data), 0, 16);
    }

    /**
     * Generate verification hash for ETA.
     */
    protected function generateEtaVerificationHash(EtaApplication $eta): string
    {
        $data = $eta->eta_number . 
                $eta->reference_number . 
                $eta->approved_at?->format('Ymd') .
                config('app.key');

        return substr(hash('sha256', $data), 0, 16);
    }
}
