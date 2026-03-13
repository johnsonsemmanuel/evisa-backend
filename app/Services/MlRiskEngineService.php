<?php

namespace App\Services;

use App\Models\Application;
use App\Models\RiskAssessment;
use App\Models\Watchlist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MlRiskEngineService
{
    protected array $featureWeights = [
        'watchlist_match' => 35,
        'nationality_risk' => 12,
        'previous_denial' => 25,
        'overstay_history' => 22,
        'document_anomaly' => 15,
        'travel_pattern_anomaly' => 10,
        'short_notice' => 8,
        'first_time_applicant' => 5,
        'high_risk_purpose' => 12,
        'age_risk' => 5,
        'employment_unverified' => 8,
        'financial_insufficient' => 10,
        'sponsor_risk' => 8,
    ];

    protected array $highRiskNationalities = ['KP', 'IR', 'SY', 'CU', 'VE', 'AF', 'IQ', 'LY', 'SO', 'YE'];
    protected array $mediumRiskNationalities = ['PK', 'BD', 'LK', 'NP', 'MM', 'ET', 'ER', 'SD'];

    /**
     * Perform ML-based risk assessment.
     */
    public function assessRisk(Application $application): array
    {
        $features = $this->extractFeatures($application);
        $score = $this->calculateRiskScore($features);
        $level = $this->determineRiskLevel($score);
        $recommendations = $this->generateRecommendations($features, $score, $level);
        $confidence = $this->calculateConfidence($features);

        // Store assessment
        $assessment = RiskAssessment::updateOrCreate(
            ['application_id' => $application->id],
            [
                'risk_score' => $score,
                'risk_level' => $level,
                'factors' => $features,
                'watchlist_match' => $features['watchlist_match']['triggered'] ?? false,
                'watchlist_matches' => $features['watchlist_match']['matches'] ?? null,
                'nationality_risk' => $features['nationality_risk']['triggered'] ?? false,
                'previous_denial' => $features['previous_denial']['triggered'] ?? false,
                'overstay_history' => $features['overstay_history']['triggered'] ?? false,
                'notes' => json_encode($recommendations),
                'status' => $level === 'critical' ? 'manual_review' : 'completed',
                'assessed_at' => now(),
            ]
        );

        // Update application
        $application->update([
            'risk_score' => $score,
            'risk_level' => $level,
            'watchlist_flagged' => $features['watchlist_match']['triggered'] ?? false,
            'risk_assessed_at' => now(),
            'risk_screening_status' => $level === 'critical' ? 'flagged' : 'cleared',
        ]);

        return [
            'score' => $score,
            'level' => $level,
            'confidence' => $confidence,
            'features' => $features,
            'recommendations' => $recommendations,
            'assessment_id' => $assessment->id,
        ];
    }

    /**
     * Extract risk features from application.
     */
    protected function extractFeatures(Application $application): array
    {
        $features = [];

        // 1. Watchlist check
        $watchlistMatches = $this->checkWatchlist($application);
        $features['watchlist_match'] = [
            'triggered' => !empty($watchlistMatches),
            'matches' => $watchlistMatches,
            'weight' => $this->featureWeights['watchlist_match'],
            'score' => !empty($watchlistMatches) ? $this->featureWeights['watchlist_match'] : 0,
        ];

        // 2. Nationality risk
        $nationality = strtoupper($application->nationality ?? '');
        $natRisk = $this->assessNationalityRisk($nationality);
        $features['nationality_risk'] = [
            'triggered' => $natRisk > 0,
            'nationality' => $nationality,
            'risk_category' => $natRisk >= 10 ? 'high' : ($natRisk > 0 ? 'medium' : 'low'),
            'weight' => $this->featureWeights['nationality_risk'],
            'score' => $natRisk,
        ];

        // 3. Previous denial history
        $previousDenial = $this->checkPreviousDenial($application);
        $features['previous_denial'] = [
            'triggered' => $previousDenial,
            'weight' => $this->featureWeights['previous_denial'],
            'score' => $previousDenial ? $this->featureWeights['previous_denial'] : 0,
        ];

        // 4. Overstay history
        $overstay = $this->checkOverstayHistory($application);
        $features['overstay_history'] = [
            'triggered' => $overstay,
            'weight' => $this->featureWeights['overstay_history'],
            'score' => $overstay ? $this->featureWeights['overstay_history'] : 0,
        ];

        // 5. Travel pattern analysis
        $travelAnomaly = $this->analyzeTravelPattern($application);
        $features['travel_pattern_anomaly'] = [
            'triggered' => $travelAnomaly['anomaly'],
            'details' => $travelAnomaly['details'],
            'weight' => $this->featureWeights['travel_pattern_anomaly'],
            'score' => $travelAnomaly['score'],
        ];

        // 6. Short notice travel
        $shortNotice = $this->checkShortNotice($application);
        $features['short_notice'] = [
            'triggered' => $shortNotice,
            'days_until_travel' => $application->intended_arrival 
                ? now()->diffInDays($application->intended_arrival, false) 
                : null,
            'weight' => $this->featureWeights['short_notice'],
            'score' => $shortNotice ? $this->featureWeights['short_notice'] : 0,
        ];

        // 7. First time applicant
        $firstTime = $this->isFirstTimeApplicant($application);
        $features['first_time_applicant'] = [
            'triggered' => $firstTime,
            'weight' => $this->featureWeights['first_time_applicant'],
            'score' => $firstTime ? $this->featureWeights['first_time_applicant'] : 0,
        ];

        // 8. Document anomaly detection
        $docAnomaly = $this->detectDocumentAnomalies($application);
        $features['document_anomaly'] = [
            'triggered' => $docAnomaly['detected'],
            'issues' => $docAnomaly['issues'],
            'weight' => $this->featureWeights['document_anomaly'],
            'score' => $docAnomaly['score'],
        ];

        // 9. Age-based risk
        $ageRisk = $this->assessAgeRisk($application);
        $features['age_risk'] = [
            'triggered' => $ageRisk > 0,
            'weight' => $this->featureWeights['age_risk'],
            'score' => $ageRisk,
        ];

        return $features;
    }

    /**
     * Calculate total risk score from features.
     */
    protected function calculateRiskScore(array $features): int
    {
        $score = 0;
        foreach ($features as $feature) {
            $score += $feature['score'] ?? 0;
        }
        return min(100, max(0, $score));
    }

    /**
     * Determine risk level from score.
     */
    protected function determineRiskLevel(int $score): string
    {
        if ($score >= 75) return 'critical';
        if ($score >= 50) return 'high';
        if ($score >= 25) return 'medium';
        return 'low';
    }

    /**
     * Calculate confidence in the assessment.
     */
    protected function calculateConfidence(array $features): float
    {
        $dataPoints = 0;
        $totalPossible = count($features);

        foreach ($features as $feature) {
            if (isset($feature['triggered'])) {
                $dataPoints++;
            }
        }

        return round(($dataPoints / $totalPossible) * 100, 1);
    }

    /**
     * Generate smart recommendations based on assessment.
     */
    protected function generateRecommendations(array $features, int $score, string $level): array
    {
        $recommendations = [];

        // Critical recommendations
        if ($features['watchlist_match']['triggered']) {
            $recommendations[] = [
                'priority' => 'critical',
                'action' => 'MANUAL_REVIEW_REQUIRED',
                'reason' => 'Watchlist match detected',
                'details' => 'Application matches ' . count($features['watchlist_match']['matches']) . ' watchlist record(s)',
            ];
        }

        if ($features['previous_denial']['triggered']) {
            $recommendations[] = [
                'priority' => 'high',
                'action' => 'VERIFY_DENIAL_REASON',
                'reason' => 'Previous visa denial on record',
                'details' => 'Review previous denial reason before approval',
            ];
        }

        if ($features['overstay_history']['triggered']) {
            $recommendations[] = [
                'priority' => 'high',
                'action' => 'ESCALATE_TO_MFA',
                'reason' => 'Immigration violation history',
                'details' => 'Previous overstay recorded - consider interview requirement',
            ];
        }

        // Medium priority recommendations
        if ($features['nationality_risk']['triggered'] && $features['nationality_risk']['risk_category'] === 'high') {
            $recommendations[] = [
                'priority' => 'medium',
                'action' => 'ENHANCED_SCREENING',
                'reason' => 'High-risk nationality',
                'details' => 'Additional verification recommended for ' . $features['nationality_risk']['nationality'],
            ];
        }

        if ($features['document_anomaly']['triggered']) {
            $recommendations[] = [
                'priority' => 'medium',
                'action' => 'VERIFY_DOCUMENTS',
                'reason' => 'Document irregularities detected',
                'details' => implode(', ', $features['document_anomaly']['issues'] ?? []),
            ];
        }

        if ($features['short_notice']['triggered']) {
            $recommendations[] = [
                'priority' => 'low',
                'action' => 'VERIFY_TRAVEL_PURPOSE',
                'reason' => 'Short notice travel',
                'details' => 'Travel date is less than 7 days away - verify urgency',
            ];
        }

        // Approval recommendations for low risk
        if ($level === 'low' && empty($recommendations)) {
            $recommendations[] = [
                'priority' => 'info',
                'action' => 'APPROVE_RECOMMENDED',
                'reason' => 'Low risk profile',
                'details' => 'No risk factors detected - suitable for standard processing',
            ];
        }

        return $recommendations;
    }

    /**
     * Check watchlist for matches.
     */
    protected function checkWatchlist(Application $application): array
    {
        return Watchlist::checkMatch(
            $application->first_name ?? '',
            $application->last_name ?? '',
            $application->passport_number,
            $application->nationality,
            $application->date_of_birth ? new \DateTime($application->date_of_birth) : null
        );
    }

    /**
     * Assess nationality risk.
     */
    protected function assessNationalityRisk(string $nationality): int
    {
        if (in_array($nationality, $this->highRiskNationalities)) {
            return $this->featureWeights['nationality_risk'];
        }
        if (in_array($nationality, $this->mediumRiskNationalities)) {
            return (int) ($this->featureWeights['nationality_risk'] * 0.5);
        }
        return 0;
    }

    /**
     * Check for previous denial.
     */
    protected function checkPreviousDenial(Application $application): bool
    {
        $passport = $application->passport_number;
        if (!$passport) return false;

        return Application::where('status', 'denied')
            ->where('id', '!=', $application->id)
            ->get()
            ->contains(function ($app) use ($passport) {
                return strtoupper($app->passport_number ?? '') === strtoupper($passport);
            });
    }

    /**
     * Check for overstay history.
     */
    protected function checkOverstayHistory(Application $application): bool
    {
        $passport = $application->passport_number;
        if (!$passport) return false;

        return Watchlist::active()
            ->where('list_type', 'overstay')
            ->get()
            ->contains(function ($entry) use ($passport) {
                return strtoupper($entry->passport_number ?? '') === strtoupper($passport);
            });
    }

    /**
     * Analyze travel patterns for anomalies.
     */
    protected function analyzeTravelPattern(Application $application): array
    {
        $anomalies = [];
        $score = 0;

        // Check for multiple applications in short period
        $recentApps = Application::where('user_id', $application->user_id)
            ->where('id', '!=', $application->id)
            ->where('created_at', '>=', now()->subMonths(3))
            ->count();

        if ($recentApps >= 3) {
            $anomalies[] = "Multiple applications ({$recentApps}) in past 3 months";
            $score += 5;
        }

        // Check for very long duration requests
        if (($application->duration_days ?? 0) > 180) {
            $anomalies[] = "Extended stay request ({$application->duration_days} days)";
            $score += 5;
        }

        return [
            'anomaly' => !empty($anomalies),
            'details' => $anomalies,
            'score' => $score,
        ];
    }

    /**
     * Check if travel is short notice.
     */
    protected function checkShortNotice(Application $application): bool
    {
        if (!$application->intended_arrival) return false;
        $days = now()->diffInDays($application->intended_arrival, false);
        return $days >= 0 && $days < 7;
    }

    /**
     * Check if first time applicant.
     */
    protected function isFirstTimeApplicant(Application $application): bool
    {
        return Application::where('user_id', $application->user_id)
            ->where('id', '!=', $application->id)
            ->where('status', 'approved')
            ->doesntExist();
    }

    /**
     * Detect document anomalies.
     */
    protected function detectDocumentAnomalies(Application $application): array
    {
        $issues = [];
        $score = 0;

        foreach ($application->documents as $doc) {
            if ($doc->verification_status === 'rejected') {
                $issues[] = "Rejected: {$doc->document_type}";
                $score += 5;
            }
            if ($doc->ocr_status === 'failed') {
                $issues[] = "OCR failed: {$doc->document_type}";
                $score += 3;
            }
        }

        // Check for missing required documents
        $requiredDocs = $application->visaType?->required_documents ?? [];
        $uploadedTypes = $application->documents->pluck('document_type')->toArray();
        
        foreach ($requiredDocs as $required) {
            if (!in_array($required, $uploadedTypes)) {
                $issues[] = "Missing: {$required}";
                $score += 5;
            }
        }

        return [
            'detected' => !empty($issues),
            'issues' => $issues,
            'score' => min($score, $this->featureWeights['document_anomaly']),
        ];
    }

    /**
     * Assess age-based risk.
     */
    protected function assessAgeRisk(Application $application): int
    {
        $dob = $application->date_of_birth;
        if (!$dob) return 0;

        try {
            $age = (new \DateTime($dob))->diff(new \DateTime())->y;
            
            // Slightly elevated risk for very young solo travelers
            if ($age >= 18 && $age <= 22) {
                return 2;
            }
        } catch (\Exception $e) {
            return 0;
        }

        return 0;
    }

    /**
     * Get risk distribution statistics.
     */
    public function getRiskStatistics(): array
    {
        return [
            'by_level' => RiskAssessment::selectRaw('risk_level, COUNT(*) as count')
                ->groupBy('risk_level')
                ->pluck('count', 'risk_level'),
            'avg_score' => RiskAssessment::avg('risk_score'),
            'flagged_today' => RiskAssessment::whereDate('assessed_at', today())
                ->whereIn('risk_level', ['high', 'critical'])
                ->count(),
            'manual_review_pending' => RiskAssessment::where('status', 'manual_review')->count(),
        ];
    }
}
