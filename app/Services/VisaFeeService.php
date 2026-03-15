<?php

namespace App\Services;

use App\Exceptions\FeeNotFoundException;
use App\Exceptions\PaymentNotAllowedException;
use App\Models\Application;
use App\Models\FeeWaiver;
use App\Models\Payment;
use App\Models\VisaFee;
use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Database-Driven Visa Fee Service
 * 
 * Calculates fees from database configuration, not hardcoded values.
 * Fees are set by Ministry directive and can be updated without code deployment.
 * 
 * SECURITY: All payment amounts MUST come from this service, never from client input.
 */
class VisaFeeService
{
    /**
     * Calculate fee for an application.
     * 
     * SECURITY CRITICAL: This is the authoritative source for payment amounts.
     * 
     * @param Application $application
     * @return int Fee amount in pesewas
     * @throws FeeNotFoundException
     */
    public function calculateFee(Application $application): int
    {
        // Use centralized cache service with graceful degradation
        $cacheKey = CacheService::visaFeesKey(
            $application->visa_type_id,
            $application->nationality ?? 'US',
            $this->getProcessingTier($application)
        );
        
        return CacheService::remember(
            $cacheKey,
            CacheService::REFERENCE_DATA_TTL,
            fn() => $this->calculateFeeFromDatabase($application),
            [CacheService::TAG_FEES, CacheService::TAG_REFERENCE_DATA]
        );
    }

    /**
     * Calculate fee from database configuration.
     * 
     * @param Application $application
     * @return int Fee amount in pesewas
     * @throws FeeNotFoundException
     */
    protected function calculateFeeFromDatabase(Application $application): int
    {
        // Step 1: Determine nationality category
        // Note: nationality is encrypted in the database
        $nationality = $application->nationality ?? 'US'; // Default to US if not set
        $nationalityCategory = $this->getNationalityCategory($nationality);
        
        // Step 2: Determine processing tier
        $processingTier = $this->getProcessingTier($application);
        
        // Step 3: Look up base fee from database
        $baseFee = $this->lookupBaseFee(
            $application->visa_type_id,
            $processingTier,
            $nationalityCategory
        );
        
        // Step 4: Apply any active waivers
        $finalFee = $this->applyWaivers(
            $baseFee,
            $nationality,
            $application->visa_type_id
        );
        
        // Log the calculation
        Log::info('Fee calculated from database', [
            'application_id' => $application->id,
            'visa_type_id' => $application->visa_type_id,
            'nationality_category' => $nationalityCategory,
            'processing_tier' => $processingTier,
            'base_fee' => $baseFee,
            'final_fee' => $finalFee,
            'waiver_applied' => $baseFee !== $finalFee,
        ]);
        
        return $finalFee;
    }

    /**
     * Look up base fee from database.
     * 
     * @param int $visaTypeId
     * @param string $processingTier
     * @param string $nationalityCategory
     * @return int Fee amount in pesewas
     * @throws FeeNotFoundException
     */
    protected function lookupBaseFee(
        int $visaTypeId,
        string $processingTier,
        string $nationalityCategory
    ): int {
        // Query for active fees matching the criteria
        $fees = VisaFee::current()
            ->forVisaType($visaTypeId)
            ->forTier($processingTier)
            ->forNationality($nationalityCategory)
            ->get();
        
        // Check for configuration errors
        if ($fees->isEmpty()) {
            Log::error('No active fee found', [
                'visa_type_id' => $visaTypeId,
                'processing_tier' => $processingTier,
                'nationality_category' => $nationalityCategory,
            ]);
            
            throw FeeNotFoundException::forApplication(
                0, // Application ID not available in this context
                $visaTypeId,
                $processingTier,
                $nationalityCategory
            );
        }
        
        if ($fees->count() > 1) {
            Log::warning('Multiple active fees found - configuration error', [
                'visa_type_id' => $visaTypeId,
                'processing_tier' => $processingTier,
                'nationality_category' => $nationalityCategory,
                'count' => $fees->count(),
                'fee_ids' => $fees->pluck('id')->toArray(),
            ]);
            
            // Use the most specific fee (prefer specific nationality over 'all')
            $fee = $fees->where('nationality_category', '!=', 'all')->first() 
                   ?? $fees->first();
        } else {
            $fee = $fees->first();
        }
        
        return $fee->amount;
    }

    /**
     * Apply any active waivers for the applicant's nationality.
     * 
     * @param int $baseFee Base fee in pesewas
     * @param string $nationality ISO country code
     * @param int $visaTypeId
     * @return int Final fee after waivers in pesewas
     */
    protected function applyWaivers(
        int $baseFee,
        string $nationality,
        int $visaTypeId
    ): int {
        // Find applicable waivers
        $waivers = FeeWaiver::current()
            ->forNationality($nationality)
            ->forVisaType($visaTypeId)
            ->get();
        
        if ($waivers->isEmpty()) {
            return $baseFee;
        }
        
        // Apply the most beneficial waiver
        $finalFee = $baseFee;
        $appliedWaiver = null;
        
        foreach ($waivers as $waiver) {
            $feeAfterWaiver = $waiver->applyWaiver($baseFee);
            
            if ($feeAfterWaiver < $finalFee) {
                $finalFee = $feeAfterWaiver;
                $appliedWaiver = $waiver;
            }
        }
        
        if ($appliedWaiver) {
            Log::info('Fee waiver applied', [
                'waiver_id' => $appliedWaiver->id,
                'waiver_name' => $appliedWaiver->name,
                'waiver_type' => $appliedWaiver->waiver_type,
                'base_fee' => $baseFee,
                'final_fee' => $finalFee,
                'discount' => $baseFee - $finalFee,
            ]);
        }
        
        return $finalFee;
    }

    /**
     * Determine nationality category for fee lookup.
     * 
     * @param string $nationality ISO country code
     * @return string Category: ecowas, african, commonwealth, other
     */
    protected function getNationalityCategory(string $nationality): string
    {
        $nationality = strtoupper($nationality);
        
        // Cache nationality category lookups (reference data, rarely changes)
        $cacheKey = CacheService::nationalityCategoryKey($nationality);
        
        return CacheService::remember(
            $cacheKey,
            CacheService::REFERENCE_DATA_TTL,
            function () use ($nationality) {
                // ECOWAS member states
                $ecowas = ['BJ', 'BF', 'CV', 'CI', 'GM', 'GH', 'GN', 'GW', 'LR', 'ML', 'NE', 'NG', 'SN', 'SL', 'TG'];
                if (in_array($nationality, $ecowas)) {
                    return 'ecowas';
                }
                
                // African countries (non-ECOWAS)
                $african = [
                    'DZ', 'AO', 'BW', 'BI', 'CM', 'CF', 'TD', 'KM', 'CG', 'CD', 'DJ', 'EG', 'GQ', 'ER', 'ET', 'GA',
                    'KE', 'LS', 'LY', 'MG', 'MW', 'MU', 'MA', 'MZ', 'NA', 'RW', 'ST', 'SC', 'SO', 'ZA', 'SS', 'SD',
                    'SZ', 'TZ', 'TN', 'UG', 'ZM', 'ZW'
                ];
                if (in_array($nationality, $african)) {
                    return 'african';
                }
                
                // Commonwealth countries
                $commonwealth = [
                    'AU', 'BD', 'BB', 'BZ', 'BN', 'CA', 'CY', 'DM', 'FJ', 'GD', 'GY', 'IN', 'JM', 'KI', 'MT', 'MV',
                    'MU', 'NZ', 'PK', 'PG', 'WS', 'SG', 'SB', 'LK', 'TO', 'TT', 'TV', 'GB', 'VU'
                ];
                if (in_array($nationality, $commonwealth)) {
                    return 'commonwealth';
                }
                
                return 'other';
            },
            [CacheService::TAG_REFERENCE_DATA]
        );
    }

    /**
     * Determine processing tier from application.
     * 
     * @param Application $application
     * @return string Tier: standard, express, emergency
     */
    protected function getProcessingTier(Application $application): string
    {
        // Map service tier codes to processing tiers
        $tierCode = $application->serviceTier?->code ?? 'standard';
        
        return match ($tierCode) {
            'priority' => 'express',
            'express' => 'emergency',
            default => 'standard',
        };
    }

    /**
     * Get fee breakdown for display purposes.
     * 
     * @param Application $application
     * @return array Fee breakdown
     */
    public function getFeeBreakdown(Application $application): array
    {
        $nationality = $application->nationality ?? 'US';
        $nationalityCategory = $this->getNationalityCategory($nationality);
        $processingTier = $this->getProcessingTier($application);
        
        $baseFee = $this->lookupBaseFee(
            $application->visa_type_id,
            $processingTier,
            $nationalityCategory
        );
        
        $finalFee = $this->applyWaivers(
            $baseFee,
            $nationality,
            $application->visa_type_id
        );
        
        $waiverAmount = $baseFee - $finalFee;
        
        return [
            'base_fee' => $baseFee,
            'base_fee_currency' => $baseFee / 100,
            'waiver_amount' => $waiverAmount,
            'waiver_amount_currency' => $waiverAmount / 100,
            'final_fee' => $finalFee,
            'final_fee_currency' => $finalFee / 100,
            'currency' => 'GHS',
            'nationality_category' => $nationalityCategory,
            'processing_tier' => $processingTier,
            'has_waiver' => $waiverAmount > 0,
        ];
    }

    /**
     * Validate that an application is eligible for payment.
     * 
     * SECURITY CRITICAL: Prevents duplicate payments and payment manipulation.
     * 
     * @param Application $application
     * @throws PaymentNotAllowedException
     * @return void
     */
    public function validatePaymentEligibility(Application $application): void
    {
        // Check 1: Application must not already have a successful payment
        $existingPayment = Payment::where('application_id', $application->id)
            ->where('status', 'paid')
            ->first();

        if ($existingPayment) {
            Log::warning('Payment attempt on already paid application', [
                'application_id' => $application->id,
                'existing_payment_id' => $existingPayment->id,
            ]);

            throw PaymentNotAllowedException::alreadyPaid($application->id);
        }

        // Check 2: Application must be in a payable state
        $payableStatuses = [
            'pending_payment',
            'submitted_awaiting_payment',
            'draft', // Allow draft for demo/testing
        ];

        if (!in_array($application->status, $payableStatuses)) {
            Log::warning('Payment attempt on non-payable application', [
                'application_id' => $application->id,
                'current_status' => $application->status,
            ]);

            throw PaymentNotAllowedException::invalidStatus(
                $application->id,
                $application->status
            );
        }

        // Check 3: Prevent rapid re-initiation (rate limiting)
        $recentPayment = Payment::where('application_id', $application->id)
            ->where('status', 'initiated')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->first();

        if ($recentPayment) {
            $secondsRemaining = 300 - now()->diffInSeconds($recentPayment->created_at);

            Log::warning('Rapid payment re-initiation attempt', [
                'application_id' => $application->id,
                'recent_payment_id' => $recentPayment->id,
                'seconds_remaining' => $secondsRemaining,
            ]);

            throw PaymentNotAllowedException::recentInitiation(
                $application->id,
                $secondsRemaining
            );
        }

        Log::info('Payment eligibility validated', [
            'application_id' => $application->id,
        ]);
    }

    /**
     * Clear fee cache for an application.
     * Call this when application details change.
     * 
     * @param int $applicationId
     * @return void
     */
    public function clearFeeCache(int $applicationId): void
    {
        // Bust fee-related caches
        CacheService::flushTags([CacheService::TAG_FEES]);
        
        Log::info('Fee cache cleared', ['application_id' => $applicationId]);
    }

    /**
     * Clear all fee caches.
     * Call this when fee configuration changes.
     * 
     * @return void
     */
    public function clearAllFeeCaches(): void
    {
        CacheService::bustReferenceData();
        
        Log::info('All fee caches cleared');
    }
}
