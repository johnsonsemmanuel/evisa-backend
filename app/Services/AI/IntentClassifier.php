<?php

namespace App\Services\AI;

class IntentClassifier
{
    /**
     * Pattern mappings for intent classification.
     */
    protected array $patterns = [
        'revenue_total' => [
            '/how much (revenue|money|income|earned)/i',
            '/total (revenue|income|earnings)/i',
            '/what.*revenue/i',
            '/revenue.*total/i',
        ],
        'revenue_breakdown' => [
            '/revenue by (visa|country|tier|type)/i',
            '/breakdown.*revenue/i',
            '/which (visa|country).*most revenue/i',
            '/revenue.*breakdown/i',
            '/revenue.*from/i',
        ],
        'revenue_trend' => [
            '/revenue trend/i',
            '/revenue over time/i',
            '/show.*revenue.*(chart|graph|trend)/i',
            '/revenue.*growth/i',
        ],
        'visitor_count' => [
            '/how many (visitors|applications|applicants)/i',
            '/total (visitors|applications|applicants)/i',
            '/number of (visitors|applications|applicants)/i',
            '/count.*applications/i',
        ],
        'visitor_breakdown' => [
            '/(visitors|applications) (by|from) country/i',
            '/which country.*most (visitors|applications)/i',
            '/applications.*breakdown/i',
            '/visitors.*from/i',
        ],
        'approval_rate' => [
            '/approval rate/i',
            '/how many.*approved/i',
            '/denial rate/i',
            '/rejection rate/i',
            '/success rate/i',
            '/approved.*denied/i',
        ],
        'comparison' => [
            '/compare.*to/i',
            '/versus|vs\.?/i',
            '/difference between/i',
            '/compared to/i',
        ],
        'peak_analysis' => [
            '/when.*highest/i',
            '/peak (period|time)/i',
            '/busiest (month|period|time)/i',
            '/most.*revenue/i',
            '/highest.*applications/i',
        ],
        'demographics' => [
            '/demographics/i',
            '/age.*group/i',
            '/gender.*breakdown/i',
            '/purpose.*visit/i',
        ],
    ];

    /**
     * Classify the intent of a natural language query.
     */
    public function classify(string $query): string
    {
        $query = trim($query);
        
        foreach ($this->patterns as $intent => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $query)) {
                    return $intent;
                }
            }
        }
        
        return 'unknown';
    }

    /**
     * Get confidence score for classification (0-100).
     */
    public function getConfidence(string $query, string $intent): int
    {
        if ($intent === 'unknown') {
            return 0;
        }

        $matchCount = 0;
        $patterns = $this->patterns[$intent] ?? [];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $query)) {
                $matchCount++;
            }
        }

        // Higher match count = higher confidence
        return min(100, 50 + ($matchCount * 25));
    }
}
