<?php

namespace App\Services;

use App\Models\Application;
use App\Models\RiskAssessment;
use App\Models\Watchlist;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Risk Screening Service
 * 
 * Phase 2: Automated risk screening with watchlist checking, 
 * document verification, and risk scoring.
 */
class RiskScreeningService
{
    protected array $highRiskNationalities = ['KP', 'IR', 'SY', 'CU', 'VE'];
    protected array $riskFactorWeights = [
        'watchlist_match' => 40,
        'high_risk_nationality' => 15,
        'previous_denial' => 25,
        'overstay_history' => 20,
        'document_issues' => 15,
        'short_notice_travel' => 10,
        'first_time_visitor' => 5,
    ];

    /**
     * Screening status values:
     * - pending: Not yet screened
     * - in_progress: Screening in progress
     * - cleared: No flags found
     * - flagged: Requires manual review
     * - failed: Screening failed (system error)
     */

    /**
     * Mark an application as cleared after manual screening.
     */
    public function markCleared(Application $application, string $notes = null): Application
    {
        $application->update([
            'risk_screening_status' => 'cleared',
            'risk_screening_notes' => $notes ?? 'Manual screening completed. No flags.',
        ]);

        Log::info("Application {$application->reference_number} cleared risk screening");

        return $application;
    }

    /**
     * Mark an application as flagged during screening.
     */
    public function markFlagged(Application $application, string $reason): Application
    {
        $application->update([
            'risk_screening_status' => 'flagged',
            'risk_screening_notes' => $reason,
        ]);

        Log::warning("Application {$application->reference_number} flagged: {$reason}");

        return $application;
    }

    /**
     * Get applications pending risk screening.
     */
    public function getPendingScreening(int $limit = 50)
    {
        return Application::where('risk_screening_status', 'pending')
            ->whereIn('status', ['under_review', 'escalated'])
            ->orderBy('submitted_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get applications flagged for manual review.
     */
    public function getFlaggedApplications()
    {
        return Application::where('risk_screening_status', 'flagged')
            ->whereNotIn('status', ['approved', 'denied', 'cancelled'])
            ->orderBy('submitted_at', 'asc')
            ->get();
    }

    /**
     * Check if an application can be approved based on screening status.
     */
    public function canApprove(Application $application): bool
    {
        // In Phase 1, we allow approval even if screening is pending
        // but flag it in the system
        return !in_array($application->risk_screening_status, ['flagged']);
    }

    /**
     * Get screening statistics for admin dashboard.
     */
    public function getStatistics(): array
    {
        return [
            'pending' => Application::where('risk_screening_status', 'pending')
                ->whereNotIn('status', ['approved', 'denied', 'cancelled'])->count(),
            'cleared' => Application::where('risk_screening_status', 'cleared')->count(),
            'flagged' => Application::where('risk_screening_status', 'flagged')
                ->whereNotIn('status', ['denied'])->count(),
            'in_progress' => Application::where('risk_screening_status', 'in_progress')->count(),
        ];
    }

    /**
     * Perform automated risk assessment on an application.
     */
    public function performRiskAssessment(Application $application): RiskAssessment
    {
        $application->update(['risk_screening_status' => 'in_progress']);

        $factors = [];
        $riskScore = 0;

        // 1. Check watchlist
        $watchlistMatches = $this->checkWatchlist($application);
        $hasWatchlistMatch = !empty($watchlistMatches);
        if ($hasWatchlistMatch) {
            $riskScore += $this->riskFactorWeights['watchlist_match'];
            $factors['watchlist_match'] = [
                'triggered' => true,
                'matches' => count($watchlistMatches),
                'weight' => $this->riskFactorWeights['watchlist_match'],
            ];
        }

        // 2. Check high-risk nationality
        $nationality = $this->getDecryptedField($application, 'nationality_encrypted');
        $isHighRiskNationality = in_array(strtoupper($nationality), $this->highRiskNationalities);
        if ($isHighRiskNationality) {
            $riskScore += $this->riskFactorWeights['high_risk_nationality'];
            $factors['high_risk_nationality'] = [
                'triggered' => true,
                'nationality' => $nationality,
                'weight' => $this->riskFactorWeights['high_risk_nationality'],
            ];
        }

        // 3. Check previous denial history
        $previousDenial = $this->checkPreviousDenial($application);
        if ($previousDenial) {
            $riskScore += $this->riskFactorWeights['previous_denial'];
            $factors['previous_denial'] = [
                'triggered' => true,
                'weight' => $this->riskFactorWeights['previous_denial'],
            ];
        }

        // 4. Check overstay history
        $overstayHistory = $this->checkOverstayHistory($application);
        if ($overstayHistory) {
            $riskScore += $this->riskFactorWeights['overstay_history'];
            $factors['overstay_history'] = [
                'triggered' => true,
                'weight' => $this->riskFactorWeights['overstay_history'],
            ];
        }

        // 5. Check short notice travel (< 7 days)
        $shortNotice = $this->checkShortNoticeTravel($application);
        if ($shortNotice) {
            $riskScore += $this->riskFactorWeights['short_notice_travel'];
            $factors['short_notice_travel'] = [
                'triggered' => true,
                'weight' => $this->riskFactorWeights['short_notice_travel'],
            ];
        }

        // 6. Document verification status
        $documentIssues = $this->checkDocumentIssues($application);
        if ($documentIssues) {
            $riskScore += $this->riskFactorWeights['document_issues'];
            $factors['document_issues'] = [
                'triggered' => true,
                'issues' => $documentIssues,
                'weight' => $this->riskFactorWeights['document_issues'],
            ];
        }

        // Calculate risk level
        $riskLevel = RiskAssessment::calculateRiskLevel($riskScore);

        // Create or update risk assessment
        $assessment = RiskAssessment::updateOrCreate(
            ['application_id' => $application->id],
            [
                'risk_score' => $riskScore,
                'risk_level' => $riskLevel,
                'factors' => $factors,
                'watchlist_match' => $hasWatchlistMatch,
                'watchlist_matches' => $watchlistMatches,
                'nationality_risk' => $isHighRiskNationality,
                'previous_denial' => $previousDenial,
                'overstay_history' => $overstayHistory,
                'status' => $riskLevel === 'critical' ? 'manual_review' : 'completed',
                'assessed_at' => now(),
            ]
        );

        // Update application with risk info
        $application->update([
            'risk_screening_status' => $hasWatchlistMatch || $riskLevel === 'critical' ? 'flagged' : 'cleared',
            'risk_score' => $riskScore,
            'risk_level' => $riskLevel,
            'watchlist_flagged' => $hasWatchlistMatch,
            'risk_assessed_at' => now(),
        ]);

        Log::info("Risk assessment completed for {$application->reference_number}: score={$riskScore}, level={$riskLevel}");

        return $assessment;
    }

    /**
     * Check application against watchlist.
     */
    protected function checkWatchlist(Application $application): array
    {
        $firstName = $this->getDecryptedField($application, 'first_name_encrypted');
        $lastName = $this->getDecryptedField($application, 'last_name_encrypted');
        $passportNumber = $this->getDecryptedField($application, 'passport_number_encrypted');
        $nationality = $this->getDecryptedField($application, 'nationality_encrypted');
        $dob = $application->date_of_birth;

        return Watchlist::checkMatch($firstName, $lastName, $passportNumber, $nationality, $dob);
    }

    /**
     * Check for previous visa denials.
     */
    protected function checkPreviousDenial(Application $application): bool
    {
        $passportNumber = $this->getDecryptedField($application, 'passport_number_encrypted');
        
        return Application::where('status', 'denied')
            ->where('id', '!=', $application->id)
            ->get()
            ->contains(function ($app) use ($passportNumber) {
                $appPassport = $this->getDecryptedField($app, 'passport_number_encrypted');
                return strtoupper($appPassport) === strtoupper($passportNumber);
            });
    }

    /**
     * Check for overstay history.
     */
    protected function checkOverstayHistory(Application $application): bool
    {
        // Check watchlist for overstay entries
        $passportNumber = $this->getDecryptedField($application, 'passport_number_encrypted');
        
        return Watchlist::active()
            ->where('list_type', 'overstay')
            ->get()
            ->contains(function ($entry) use ($passportNumber) {
                return strtoupper($entry->passport_number) === strtoupper($passportNumber);
            });
    }

    /**
     * Check if travel is short notice (less than 7 days).
     */
    protected function checkShortNoticeTravel(Application $application): bool
    {
        if (!$application->intended_arrival) {
            return false;
        }
        
        $daysUntilTravel = now()->diffInDays($application->intended_arrival, false);
        return $daysUntilTravel >= 0 && $daysUntilTravel < 7;
    }

    /**
     * Check for document verification issues.
     */
    protected function checkDocumentIssues(Application $application): ?array
    {
        $issues = [];
        
        foreach ($application->documents as $doc) {
            if ($doc->verification_status === 'rejected') {
                $issues[] = [
                    'document_type' => $doc->document_type,
                    'reason' => $doc->rejection_reason,
                ];
            }
        }

        return !empty($issues) ? $issues : null;
    }

    /**
     * Get decrypted field value from application.
     */
    protected function getDecryptedField(Application $application, string $field): ?string
    {
        return $application->getAttribute($field);
    }

    /**
     * Batch process pending applications for risk screening.
     */
    public function batchProcessPending(int $limit = 50): array
    {
        $applications = $this->getPendingScreening($limit);
        $results = ['processed' => 0, 'flagged' => 0, 'cleared' => 0, 'errors' => 0];

        foreach ($applications as $application) {
            try {
                $assessment = $this->performRiskAssessment($application);
                $results['processed']++;
                
                if ($assessment->risk_level === 'critical' || $assessment->watchlist_match) {
                    $results['flagged']++;
                } else {
                    $results['cleared']++;
                }
            } catch (\Exception $e) {
                Log::error("Risk assessment failed for {$application->reference_number}: {$e->getMessage()}");
                $results['errors']++;
            }
        }

        return $results;
    }
}
