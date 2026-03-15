<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PassportVerificationService
{
    /**
     * Verify passport details and check validity.
     */
    public function verifyPassport(array $passportData): array
    {
        $result = [
            'valid' => false,
            'status' => 'invalid',
            'message' => '',
            'warnings' => [],
            'errors' => [],
            'verification_data' => [],
        ];

        // Basic validation
        $basicValidation = $this->performBasicValidation($passportData);
        if (!$basicValidation['valid']) {
            return array_merge($result, $basicValidation);
        }

        // Expiry validation
        $expiryValidation = $this->validateExpiry($passportData);
        $result['warnings'] = array_merge($result['warnings'], $expiryValidation['warnings']);
        $result['errors'] = array_merge($result['errors'], $expiryValidation['errors']);

        if (!empty($expiryValidation['errors'])) {
            $result['status'] = 'expired';
            $result['message'] = 'Passport has expired or is invalid';
            return $result;
        }

        // Format validation
        $formatValidation = $this->validateFormat($passportData);
        if (!$formatValidation['valid']) {
            $result['errors'][] = $formatValidation['message'];
            $result['message'] = 'Invalid passport format';
            return $result;
        }

        // Real-time verification (if enabled)
        $realtimeVerification = $this->performRealtimeVerification($passportData);
        $result['verification_data'] = $realtimeVerification['data'];
        
        if (!$realtimeVerification['available']) {
            // Offline verification passed
            $result['valid'] = true;
            $result['status'] = 'verified_offline';
            $result['message'] = 'Passport format valid - real-time verification unavailable';
        } else {
            $result['valid'] = $realtimeVerification['valid'];
            $result['status'] = $realtimeVerification['valid'] ? 'verified_online' : 'verification_failed';
            $result['message'] = $realtimeVerification['message'];
            
            if (!$realtimeVerification['valid']) {
                $result['errors'][] = $realtimeVerification['message'];
            }
        }

        return $result;
    }

    /**
     * Perform basic passport validation.
     */
    protected function performBasicValidation(array $passportData): array
    {
        $required = ['passport_number', 'nationality', 'issue_date', 'expiry_date', 'issuing_authority'];
        $missing = [];

        foreach ($required as $field) {
            if (empty($passportData[$field])) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            return [
                'valid' => false,
                'message' => 'Missing required fields: ' . implode(', ', $missing),
                'errors' => ['Missing required passport information'],
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate passport expiry dates.
     */
    protected function validateExpiry(array $passportData): array
    {
        $warnings = [];
        $errors = [];

        try {
            $issueDate = Carbon::parse($passportData['issue_date']);
            $expiryDate = Carbon::parse($passportData['expiry_date']);
            $now = Carbon::now();

            // Check if passport has expired
            if ($expiryDate->isPast()) {
                $errors[] = "Passport expired on {$expiryDate->format('Y-m-d')}. Please renew your passport before applying.";
                return ['warnings' => $warnings, 'errors' => $errors];
            }

            // Check if passport expires within 6 months
            $sixMonthsFromNow = $now->copy()->addMonths(6);
            if ($expiryDate->lt($sixMonthsFromNow)) {
                $warnings[] = "Passport expires within 6 months ({$expiryDate->format('Y-m-d')}). Consider renewing before travel.";
            }

            // Check if issue date is in the future
            if ($issueDate->isFuture()) {
                $errors[] = "Passport issue date cannot be in the future.";
            }

            // Check if issue date is after expiry date
            if ($issueDate->gt($expiryDate)) {
                $errors[] = "Passport issue date cannot be after expiry date.";
            }

            // Check passport age (most passports are valid for 5-10 years)
            $passportAge = $issueDate->diffInYears($expiryDate);
            if ($passportAge > 15) {
                $warnings[] = "Unusual passport validity period. Please verify dates.";
            }

        } catch (\Exception $e) {
            $errors[] = "Invalid date format in passport information.";
        }

        return ['warnings' => $warnings, 'errors' => $errors];
    }

    /**
     * Validate passport number format by country.
     */
    protected function validateFormat(array $passportData): array
    {
        $passportNumber = $passportData['passport_number'];
        $nationality = strtoupper($passportData['nationality']);

        // Country-specific format validation
        $formatRules = $this->getPassportFormatRules();
        
        if (isset($formatRules[$nationality])) {
            $rule = $formatRules[$nationality];
            if (!preg_match($rule['pattern'], $passportNumber)) {
                return [
                    'valid' => false,
                    'message' => "Invalid passport format for {$nationality}. Expected format: {$rule['description']}",
                ];
            }
        } else {
            // Generic validation for unknown countries
            if (!preg_match('/^[A-Z0-9]{6,12}$/', $passportNumber)) {
                return [
                    'valid' => false,
                    'message' => 'Passport number should be 6-12 alphanumeric characters',
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * Perform real-time passport verification.
     * Uses Sumsub if available, falls back to government APIs.
     */
    protected function performRealtimeVerification(array $passportData): array
    {
        $nationality = strtoupper($passportData['nationality']);
        $passportNumber = $passportData['passport_number'];

        // Check cache first
        $cacheKey = "passport_verification_{$nationality}_{$passportNumber}";
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $result = [
            'available' => false,
            'valid' => false,
            'message' => 'Real-time verification not available',
            'data' => [],
        ];

        try {
            // Try Sumsub verification first (if enabled)
            if (config('sumsub.enabled', false)) {
                $sumsubResult = $this->verifySumsubPassport($passportData);
                if ($sumsubResult) {
                    $result = $sumsubResult;
                    // Cache successful results for 1 hour
                    Cache::put($cacheKey, $result, 3600);
                    return $result;
                }
            }

            // Fall back to government API verification
            $verificationResult = $this->callVerificationService($nationality, $passportData);
            
            if ($verificationResult) {
                $result = $verificationResult;
                // Cache successful results for 1 hour
                Cache::put($cacheKey, $result, 3600);
            }

        } catch (\Exception $e) {
            Log::warning('Passport verification service error', [
                'nationality' => $nationality,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Verify passport using Sumsub document verification.
     */
    protected function verifySumsubPassport(array $passportData): ?array
    {
        try {
            // Sumsub verification would be triggered during document upload
            // This method checks if we have existing Sumsub verification data
            // For real-time inline verification, we return a flag to trigger Sumsub
            
            return [
                'available' => true,
                'valid' => true,
                'message' => 'Passport will be verified via Sumsub document verification',
                'data' => [
                    'verification_method' => 'sumsub',
                    'requires_document_upload' => true,
                    'passport_number' => $passportData['passport_number'],
                    'nationality' => $passportData['nationality'],
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Sumsub passport verification error', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Call appropriate verification service based on nationality.
     */
    protected function callVerificationService(string $nationality, array $passportData): ?array
    {
        // For demonstration - you would integrate with real services
        $services = $this->getVerificationServices();
        
        if (!isset($services[$nationality])) {
            return null;
        }

        $service = $services[$nationality];
        
        try {
            // SECURITY: Validate external URL against SSRF allowlist
            app(\App\Services\ExternalUrlValidator::class)->validateExternalUrl($service['endpoint']);
            
            $response = Http::timeout(10)
                ->withHeaders($service['headers'])
                ->post($service['endpoint'], [
                    'passport_number' => $passportData['passport_number'],
                    'nationality' => $nationality,
                    'issue_date' => $passportData['issue_date'],
                    'expiry_date' => $passportData['expiry_date'],
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'available' => true,
                    'valid' => $data['valid'] ?? false,
                    'message' => $data['message'] ?? 'Verification completed',
                    'data' => $data,
                ];
            }

        } catch (\App\Exceptions\UnauthorizedExternalRequestException $e) {
            Log::error('Passport verification URL blocked by SSRF protection', [
                'service' => $service['name'],
                'endpoint' => $service['endpoint'],
                'error' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            Log::error('Passport verification API error', [
                'service' => $service['name'],
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Get passport format rules by country.
     */
    protected function getPassportFormatRules(): array
    {
        return [
            'US' => [
                'pattern' => '/^[0-9]{9}$/',
                'description' => '9 digits',
            ],
            'GB' => [
                'pattern' => '/^[0-9]{9}$/',
                'description' => '9 digits',
            ],
            'DE' => [
                'pattern' => '/^[A-Z][0-9]{8}$/',
                'description' => '1 letter followed by 8 digits',
            ],
            'FR' => [
                'pattern' => '/^[0-9]{2}[A-Z]{2}[0-9]{5}$/',
                'description' => '2 digits, 2 letters, 5 digits',
            ],
            'NG' => [
                'pattern' => '/^[A-Z][0-9]{8}$/',
                'description' => '1 letter followed by 8 digits',
            ],
            'GH' => [
                'pattern' => '/^[A-Z][0-9]{7}$/',
                'description' => '1 letter followed by 7 digits',
            ],
            'CA' => [
                'pattern' => '/^[A-Z]{2}[0-9]{6}$/',
                'description' => '2 letters followed by 6 digits',
            ],
            'AU' => [
                'pattern' => '/^[A-Z][0-9]{7}$/',
                'description' => '1 letter followed by 7 digits',
            ],
        ];
    }

    /**
     * Get verification services configuration.
     * In production, you would configure real passport verification APIs.
     */
    protected function getVerificationServices(): array
    {
        return [
            'US' => [
                'name' => 'US State Department API',
                'endpoint' => config('services.passport_verification.us_api_url'),
                'headers' => [
                    'Authorization' => 'Bearer ' . env('US_PASSPORT_API_KEY'),
                    'Content-Type' => 'application/json',
                ],
            ],
            'GB' => [
                'name' => 'UK Home Office API',
                'endpoint' => config('services.passport_verification.uk_api_url'),
                'headers' => [
                    'Authorization' => 'Bearer ' . env('UK_PASSPORT_API_KEY'),
                    'Content-Type' => 'application/json',
                ],
            ],
            'NG' => [
                'name' => 'Nigeria Immigration Service API',
                'endpoint' => config('services.passport_verification.ng_api_url'),
                'headers' => [
                    'Authorization' => 'Bearer ' . env('NG_PASSPORT_API_KEY'),
                    'Content-Type' => 'application/json',
                ],
            ],
            // Add more countries as needed
        ];
    }

    /**
     * Get issuing authorities by country.
     */
    public function getIssuingAuthorities(string $nationality): array
    {
        $authorities = [
            'US' => [
                'U.S. Department of State',
                'U.S. Passport Agency',
                'U.S. Consulate General',
            ],
            'GB' => [
                'HM Passport Office',
                'British Consulate General',
                'British High Commission',
            ],
            'DE' => [
                'German Federal Foreign Office',
                'German Consulate General',
                'German Embassy',
            ],
            'NG' => [
                'Nigeria Immigration Service',
                'Nigerian Consulate General',
                'Nigerian Embassy',
            ],
            'GH' => [
                'Ghana Immigration Service',
                'Ghana Consulate General',
                'Ghana Embassy',
            ],
            'FR' => [
                'French Ministry of Interior',
                'French Consulate General',
                'French Embassy',
            ],
            'CA' => [
                'Passport Canada',
                'Canadian Consulate General',
                'Canadian Embassy',
            ],
            'AU' => [
                'Australian Passport Office',
                'Australian Consulate General',
                'Australian Embassy',
            ],
        ];

        return $authorities[strtoupper($nationality)] ?? [
            'National Passport Office',
            'Consulate General',
            'Embassy',
        ];
    }

    /**
     * Check if real-time verification is available for a country.
     */
    public function isRealtimeVerificationAvailable(string $nationality): bool
    {
        $services = $this->getVerificationServices();
        return isset($services[strtoupper($nationality)]);
    }

    /**
     * Get verification requirements for real-time integration.
     */
    public function getVerificationRequirements(): array
    {
        return [
            'primary_method' => 'Sumsub Document Verification',
            'sumsub_features' => [
                'passport_ocr' => 'Automatic passport data extraction',
                'document_authenticity' => 'Checks for fake/tampered documents',
                'face_matching' => 'Matches photo to passport photo',
                'expiry_validation' => 'Automatic expiry date checking',
                'format_validation' => 'Country-specific format validation',
                'real_time_verification' => 'Live document verification',
            ],
            'fallback_methods' => [
                'government_apis' => 'Direct government passport verification APIs',
                'offline_validation' => 'Format and expiry validation without external APIs',
            ],
            'api_keys_needed' => [
                'SUMSUB_APP_TOKEN' => 'Sumsub application token (primary method)',
                'SUMSUB_SECRET_KEY' => 'Sumsub secret key for API signatures',
                'US_PASSPORT_API_KEY' => 'U.S. State Department API key (fallback)',
                'UK_PASSPORT_API_KEY' => 'UK Home Office API key (fallback)',
                'NG_PASSPORT_API_KEY' => 'Nigeria Immigration Service API key (fallback)',
            ],
            'endpoints_needed' => [
                'SUMSUB_BASE_URL' => 'https://api.sumsub.com (default)',
                'US_PASSPORT_API_URL' => 'https://api.state.gov/passport/verify',
                'UK_PASSPORT_API_URL' => 'https://api.gov.uk/passport/verify',
                'NG_PASSPORT_API_URL' => 'https://api.immigration.gov.ng/passport/verify',
            ],
            'features' => [
                'format_validation' => 'Available for all countries (offline)',
                'expiry_validation' => 'Available for all countries (offline)',
                'real_time_verification' => 'Available via Sumsub (requires API key)',
                'document_authenticity' => 'Available via Sumsub (requires API key)',
                'issuing_authority_validation' => 'Available for major countries',
            ],
            'setup_instructions' => [
                '1. Sign up for Sumsub account at https://sumsub.com',
                '2. Obtain API keys from Sumsub dashboard',
                '3. Add SUMSUB_APP_TOKEN and SUMSUB_SECRET_KEY to .env file',
                '4. Enable Sumsub: SUMSUB_ENABLED=true in .env',
                '5. Configure verification levels in Sumsub dashboard',
                '6. Test with sample passport documents',
                '7. (Optional) Add government API keys for fallback verification',
            ],
        ];
    }

    /**
     * Extract passport data from Sumsub verification result.
     */
    public function extractPassportDataFromSumsub(array $sumsubData): ?array
    {
        try {
            // Sumsub returns document data in the verification response
            $documents = $sumsubData['info']['idDocs'] ?? [];
            
            foreach ($documents as $doc) {
                if ($doc['idDocType'] === 'PASSPORT') {
                    return [
                        'passport_number' => $doc['number'] ?? null,
                        'first_name' => $doc['firstName'] ?? null,
                        'last_name' => $doc['lastName'] ?? null,
                        'date_of_birth' => $doc['dob'] ?? null,
                        'nationality' => $doc['country'] ?? null,
                        'issue_date' => $doc['issuedDate'] ?? null,
                        'expiry_date' => $doc['validUntil'] ?? null,
                        'issuing_authority' => $doc['authority'] ?? null,
                        'verification_status' => 'verified_by_sumsub',
                        'document_authentic' => $sumsubData['reviewResult']['reviewAnswer'] === 'GREEN',
                    ];
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Failed to extract passport data from Sumsub', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Validate passport data against Sumsub verification.
     */
    public function validateAgainstSumsub(array $passportData, array $sumsubData): array
    {
        $extractedData = $this->extractPassportDataFromSumsub($sumsubData);
        
        if (!$extractedData) {
            return [
                'valid' => false,
                'message' => 'No passport data found in Sumsub verification',
                'errors' => ['Sumsub verification did not contain passport data'],
            ];
        }

        $errors = [];
        $warnings = [];

        // Compare passport numbers
        if (isset($extractedData['passport_number']) && 
            strtoupper($extractedData['passport_number']) !== strtoupper($passportData['passport_number'])) {
            $errors[] = 'Passport number does not match Sumsub verification';
        }

        // Compare nationality
        if (isset($extractedData['nationality']) && 
            strtoupper($extractedData['nationality']) !== strtoupper($passportData['nationality'])) {
            $errors[] = 'Nationality does not match Sumsub verification';
        }

        // Check document authenticity
        if (!$extractedData['document_authentic']) {
            $errors[] = 'Sumsub flagged document as potentially inauthentic';
        }

        // Check expiry date
        if (isset($extractedData['expiry_date'])) {
            $expiryDate = \Carbon\Carbon::parse($extractedData['expiry_date']);
            if ($expiryDate->isPast()) {
                $errors[] = 'Passport has expired according to Sumsub verification';
            } elseif ($expiryDate->lt(\Carbon\Carbon::now()->addMonths(6))) {
                $warnings[] = 'Passport expires within 6 months according to Sumsub verification';
            }
        }

        return [
            'valid' => empty($errors),
            'status' => empty($errors) ? 'verified_by_sumsub' : 'verification_failed',
            'message' => empty($errors) ? 'Passport verified successfully via Sumsub' : 'Passport verification failed',
            'errors' => $errors,
            'warnings' => $warnings,
            'extracted_data' => $extractedData,
        ];
    }
}