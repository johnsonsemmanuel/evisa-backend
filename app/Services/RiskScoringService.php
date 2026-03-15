<?php

namespace App\Services;

use App\Models\Application;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class RiskScoringService
{
    /**
     * Calculate risk score for an application.
     */
    public function calculateRiskScore(Application $application): array
    {
        // FIX: Eager load documents to prevent N+1
        if (!$application->relationLoaded('documents')) {
            $application->load('documents:id,application_id,document_type,verification_status');
        }

        $score = 0;
        $reasons = [];

        // Identity & Passport Rules (Max 30 points)
        $identityScore = $this->calculateIdentityRisk($application, $reasons);
        $score += $identityScore;

        // Travel Pattern Rules (Max 20 points)
        $travelScore = $this->calculateTravelPatternRisk($application, $reasons);
        $score += $travelScore;

        // Financial/Sponsorship Rules (Max 20 points)
        $financialScore = $this->calculateFinancialRisk($application, $reasons);
        $score += $financialScore;

        // Immigration History Rules (Max 20 points)
        $immigrationScore = $this->calculateImmigrationHistoryRisk($application, $reasons);
        $score += $immigrationScore;

        // Document Quality Rules (Max 10 points)
        $documentScore = $this->calculateDocumentQualityRisk($application, $reasons);
        $score += $documentScore;

        // Cap at 100
        $score = min($score, 100);

        // Determine risk level
        $riskLevel = $this->determineRiskLevel($score);

        // Keep only top 5 reasons
        $topReasons = array_slice($reasons, 0, 5);

        Log::info('Risk score calculated', [
            'application_id' => $application->id,
            'risk_score' => $score,
            'risk_level' => $riskLevel,
            'reasons_count' => count($topReasons),
        ]);

        return [
            'risk_score' => $score,
            'risk_level' => $riskLevel,
            'risk_reasons' => $topReasons,
            'risk_last_updated' => now(),
        ];
    }

    /**
     * Identity & Passport Risk Rules (Max 30 points).
     */
    protected function calculateIdentityRisk(Application $application, array &$reasons): int
    {
        $score = 0;

        // Passport expires < 6 months after arrival (+25 points)
        if ($application->passport_expiry_date) {
            $arrivalDate = $application->arrival_date ? Carbon::parse($application->arrival_date) : now();
            $passportExpiry = Carbon::parse($application->passport_expiry_date);
            $monthsUntilExpiry = $arrivalDate->diffInMonths($passportExpiry);

            if ($monthsUntilExpiry < 6) {
                $score += 25;
                $reasons[] = "Passport expires within 6 months of arrival";
            }
        }

        // Passport name mismatch with application (+20 points)
        // This would require OCR comparison - placeholder for now
        if ($this->hasNameMismatch($application)) {
            $score += 20;
            $reasons[] = "Passport name mismatch with application";
        }

        // Nationality flagged for additional review (+15 points)
        if ($this->isNationalityFlagged($application->nationality)) {
            $score += 15;
            $reasons[] = "Nationality flagged for additional review";
        }

        // Passport issued < 6 months ago (+10 points)
        if ($application->passport_issue_date) {
            $issueDate = Carbon::parse($application->passport_issue_date);
            $monthsSinceIssue = $issueDate->diffInMonths(now());

            if ($monthsSinceIssue < 6) {
                $score += 10;
                $reasons[] = "Passport issued less than 6 months ago";
            }
        }

        return min($score, 30); // Cap at 30 points
    }

    /**
     * Travel Pattern Risk Rules (Max 20 points).
     */
    protected function calculateTravelPatternRisk(Application $application, array &$reasons): int
    {
        $score = 0;

        // Stay duration inconsistent with visa type (+15 points)
        if ($this->hasInconsistentStayDuration($application)) {
            $score += 15;
            $reasons[] = "Stay duration inconsistent with visa type";
        }

        // Travel purpose inconsistent with documents (+15 points)
        if ($this->hasInconsistentTravelPurpose($application)) {
            $score += 15;
            $reasons[] = "Travel purpose inconsistent with documents";
        }

        // No return ticket uploaded (+10 points)
        if (!$this->hasReturnTicket($application)) {
            $score += 10;
            $reasons[] = "No return ticket uploaded";
        }

        // Accommodation missing or unclear (+10 points)
        if (!$this->hasValidAccommodation($application)) {
            $score += 10;
            $reasons[] = "Accommodation information missing or unclear";
        }

        return min($score, 20); // Cap at 20 points
    }

    /**
     * Financial/Sponsorship Risk Rules (Max 20 points).
     */
    protected function calculateFinancialRisk(Application $application, array &$reasons): int
    {
        $score = 0;

        // Proof of funds missing (+20 points)
        if (!$this->hasProofOfFunds($application)) {
            $score += 20;
            $reasons[] = "Proof of funds missing";
        }

        // Invitation letter missing for business visa (+15 points)
        if ($this->isBusinessVisa($application) && !$this->hasInvitationLetter($application)) {
            $score += 15;
            $reasons[] = "Invitation letter missing for business visa";
        }

        // Bank statement under verification (+10 points)
        if ($this->isBankStatementUnderVerification($application)) {
            $score += 10;
            $reasons[] = "Bank statement under verification";
        }

        // Sponsor details incomplete (+10 points)
        if ($this->hasSponsor($application) && !$this->hasCompleteSponsorDetails($application)) {
            $score += 10;
            $reasons[] = "Sponsor details incomplete";
        }

        return min($score, 20); // Cap at 20 points
    }

    /**
     * Immigration History Risk Rules (Max 20 points).
     */
    protected function calculateImmigrationHistoryRisk(Application $application, array &$reasons): int
    {
        $score = 0;

        // Prior deportation (+40 points)
        if ($application->prior_deportation === 'yes') {
            $score += 40;
            $reasons[] = "Prior deportation declared";
        }

        // Prior Ghana overstay (+30 points)
        if ($application->prior_overstay === 'yes') {
            $score += 30;
            $reasons[] = "Prior overstay in Ghana declared";
        }

        // Inconsistent immigration declarations (+25 points)
        if ($this->hasInconsistentDeclarations($application)) {
            $score += 25;
            $reasons[] = "Inconsistent immigration declarations";
        }

        // Prior visa refusal declared (+20 points)
        if ($application->prior_refusal === 'yes') {
            $score += 20;
            $reasons[] = "Prior visa refusal declared";
        }

        return min($score, 20); // Cap at 20 points
    }

    /**
     * Document Quality Risk Rules (Max 10 points).
     */
    protected function calculateDocumentQualityRisk(Application $application, array &$reasons): int
    {
        $score = 0;

        // Required document missing (+15 points)
        $missingDocs = $this->getMissingRequiredDocuments($application);
        if (count($missingDocs) > 0) {
            $score += 15;
            $reasons[] = "Required documents missing: " . implode(', ', $missingDocs);
        }

        // Check document quality issues
        $qualityIssues = $this->getDocumentQualityIssues($application);
        foreach ($qualityIssues as $issue) {
            $score += $issue['points'];
            $reasons[] = $issue['reason'];
        }

        return min($score, 10); // Cap at 10 points
    }

    /**
     * Determine risk level based on score.
     */
    protected function determineRiskLevel(int $score): string
    {
        if ($score <= 24) return 'Low';
        if ($score <= 49) return 'Medium';
        if ($score <= 74) return 'High';
        return 'Critical';
    }

    /**
     * Update application with calculated risk score.
     */
    public function updateApplicationRiskScore(Application $application): void
    {
        $riskData = $this->calculateRiskScore($application);
        
        $application->forceFill([
            'risk_score' => $riskData['risk_score'],
            'risk_level' => $riskData['risk_level'],
            'risk_reasons' => $riskData['risk_reasons'],
            'risk_last_updated' => $riskData['risk_last_updated'],
        ])->save();
    }

    // Helper methods for risk assessment

    protected function hasNameMismatch(Application $application): bool
    {
        // Check if passport scan exists and has been OCR processed
        if (!$application->passport_scan_path || !$application->ocr_data) {
            return false;
        }

        try {
            $ocrData = is_string($application->ocr_data) 
                ? json_decode($application->ocr_data, true) 
                : $application->ocr_data;

            if (!isset($ocrData['name'])) {
                return false;
            }

            // Normalize names for comparison
            $applicationName = strtoupper(trim($application->first_name . ' ' . $application->last_name));
            $passportName = strtoupper(trim($ocrData['name']));

            // Calculate similarity using Levenshtein distance
            $similarity = similar_text($applicationName, $passportName, $percent);
            
            // Flag if similarity is less than 80%
            return $percent < 80;

        } catch (\Exception $e) {
            Log::warning('Name mismatch check failed', [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    protected function isNationalityFlagged(string $nationality): bool
    {
        // List of nationalities requiring additional review
        $flaggedNationalities = ['AF', 'IQ', 'IR', 'KP', 'LY', 'SO', 'SY', 'YE'];
        return in_array($nationality, $flaggedNationalities);
    }

    protected function hasInconsistentStayDuration(Application $application): bool
    {
        if (!$application->arrival_date || !$application->departure_date) {
            return false;
        }

        $stayDuration = Carbon::parse($application->arrival_date)
            ->diffInDays(Carbon::parse($application->departure_date));

        // Business visa: typically 7-30 days
        // Tourism visa: typically 7-90 days
        if ($this->isBusinessVisa($application) && $stayDuration > 30) {
            return true;
        }

        if ($this->isTourismVisa($application) && $stayDuration > 90) {
            return true;
        }

        return false;
    }

    protected function hasInconsistentTravelPurpose(Application $application): bool
    {
        $visaType = $application->visaType?->slug;
        $purpose = strtolower($application->purpose_of_visit ?? '');

        // Business visa checks
        if ($this->isBusinessVisa($application)) {
            // Business visa should have invitation letter
            if (!$this->hasInvitationLetter($application)) {
                return true;
            }

            // Purpose should mention business-related keywords
            $businessKeywords = ['business', 'meeting', 'conference', 'trade', 'investment', 'company'];
            $hasBusinessKeyword = false;
            foreach ($businessKeywords as $keyword) {
                if (str_contains($purpose, $keyword)) {
                    $hasBusinessKeyword = true;
                    break;
                }
            }

            if (!$hasBusinessKeyword && !empty($purpose)) {
                return true;
            }
        }

        // Tourism visa checks
        if ($this->isTourismVisa($application)) {
            // Tourism visa should have hotel booking or accommodation proof
            if (!$this->hasValidAccommodation($application)) {
                return true;
            }

            // Purpose should mention tourism-related keywords
            $tourismKeywords = ['tourism', 'vacation', 'holiday', 'visit', 'sightseeing', 'travel', 'leisure'];
            $hasTourismKeyword = false;
            foreach ($tourismKeywords as $keyword) {
                if (str_contains($purpose, $keyword)) {
                    $hasTourismKeyword = true;
                    break;
                }
            }

            if (!$hasTourismKeyword && !empty($purpose)) {
                return true;
            }
        }

        // Student visa checks
        if ($visaType === 'student') {
            $hasAdmissionLetter = $application->documents()
                ->where('document_type', 'admission_letter')
                ->exists();

            if (!$hasAdmissionLetter) {
                return true;
            }

            $studentKeywords = ['study', 'student', 'education', 'university', 'college', 'school'];
            $hasStudentKeyword = false;
            foreach ($studentKeywords as $keyword) {
                if (str_contains($purpose, $keyword)) {
                    $hasStudentKeyword = true;
                    break;
                }
            }

            if (!$hasStudentKeyword && !empty($purpose)) {
                return true;
            }
        }

        return false;
    }

    protected function hasReturnTicket(Application $application): bool
    {
        // Check if return ticket document is uploaded
        return $application->documents()
            ->where('document_type', 'return_ticket')
            ->exists();
    }

    protected function hasValidAccommodation(Application $application): bool
    {
        return !empty($application->accommodation_details) ||
               $application->documents()
                   ->where('document_type', 'accommodation_proof')
                   ->exists();
    }

    protected function hasProofOfFunds(Application $application): bool
    {
        return $application->documents()
            ->whereIn('document_type', ['bank_statement', 'financial_proof'])
            ->exists();
    }

    protected function isBusinessVisa(Application $application): bool
    {
        return $application->visaType && 
               strtolower($application->visaType->name) === 'business';
    }

    protected function isTourismVisa(Application $application): bool
    {
        return $application->visaType && 
               strtolower($application->visaType->name) === 'tourism';
    }

    protected function hasInvitationLetter(Application $application): bool
    {
        return $application->documents()
            ->where('document_type', 'invitation_letter')
            ->exists();
    }

    protected function isBankStatementUnderVerification(Application $application): bool
    {
        // Placeholder - would check verification status
        return false;
    }

    protected function hasSponsor(Application $application): bool
    {
        return !empty($application->sponsor_name);
    }

    protected function hasCompleteSponsorDetails(Application $application): bool
    {
        return !empty($application->sponsor_name) &&
               !empty($application->sponsor_address) &&
               !empty($application->sponsor_phone);
    }

    protected function hasInconsistentDeclarations(Application $application): bool
    {
        // Check for logical inconsistencies in declarations

        // If declared prior deportation but also declared no prior refusal
        if ($application->prior_deportation === 'yes' && $application->prior_refusal === 'no') {
            // Deportation usually results in visa refusal - inconsistent
            return true;
        }

        // If declared criminal conviction but also declared good character
        if ($application->criminal_conviction === 'yes' && $application->good_character === 'yes') {
            return true;
        }

        // If declared prior overstay but also declared compliance with immigration laws
        if ($application->prior_overstay === 'yes' && $application->immigration_compliance === 'yes') {
            return true;
        }

        // Check travel history consistency
        if ($application->visited_other_countries === 'yes') {
            $countriesListed = array_filter([
                $application->visited_country_1,
                $application->visited_country_2,
                $application->visited_country_3,
            ]);

            if (empty($countriesListed)) {
                // Declared visited countries but didn't list any
                return true;
            }
        }

        // Check employment consistency
        if ($application->employment_status === 'unemployed' && $this->isBusinessVisa($application)) {
            // Unemployed but applying for business visa - suspicious
            return true;
        }

        // Check financial consistency
        if ($application->financial_support === 'self' && !$this->hasProofOfFunds($application)) {
            // Claims self-funded but no proof of funds
            return true;
        }

        if ($application->financial_support === 'sponsor' && !$this->hasSponsor($application)) {
            // Claims sponsored but no sponsor details
            return true;
        }

        return false;
    }

    protected function getMissingRequiredDocuments(Application $application): array
    {
        $required = ['passport_copy', 'photo'];
        // FIX: Use loaded relationship instead of query
        $uploaded = $application->documents->pluck('document_type')->toArray();
        
        return array_diff($required, $uploaded);
    }

    protected function getDocumentQualityIssues(Application $application): array
    {
        $issues = [];
        
        // Placeholder - would implement document quality checks
        // Example:
        // if (document is blurry) $issues[] = ['points' => 10, 'reason' => 'Blurry document uploaded'];
        
        return $issues;
    }
}