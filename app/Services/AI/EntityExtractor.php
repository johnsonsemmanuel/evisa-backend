<?php

namespace App\Services\AI;

use Carbon\Carbon;

class EntityExtractor
{
    /**
     * Time period patterns.
     */
    protected array $timePatterns = [
        'last month' => ['start' => '-1 month', 'end' => 'now', 'period' => 'last_month'],
        'this month' => ['start' => 'first day of this month', 'end' => 'now', 'period' => 'this_month'],
        'last year' => ['start' => '-1 year', 'end' => 'now', 'period' => 'last_year'],
        'this year' => ['start' => 'first day of January', 'end' => 'now', 'period' => 'this_year'],
        'last week' => ['start' => '-1 week', 'end' => 'now', 'period' => 'last_week'],
        'this week' => ['start' => 'monday this week', 'end' => 'now', 'period' => 'this_week'],
        'last 30 days' => ['start' => '-30 days', 'end' => 'now', 'period' => 'last_30_days'],
        'last 7 days' => ['start' => '-7 days', 'end' => 'now', 'period' => 'last_7_days'],
        'today' => ['start' => 'today', 'end' => 'now', 'period' => 'today'],
        'yesterday' => ['start' => 'yesterday', 'end' => 'yesterday 23:59:59', 'period' => 'yesterday'],
    ];

    /**
     * Country name aliases.
     */
    protected array $countryAliases = [
        'US' => ['USA', 'United States', 'America', 'US'],
        'UK' => ['United Kingdom', 'Britain', 'England', 'UK'],
        'NG' => ['Nigeria', 'Nigerian'],
        'GH' => ['Ghana', 'Ghanaian'],
        'ZA' => ['South Africa', 'South African'],
        'KE' => ['Kenya', 'Kenyan'],
        'CA' => ['Canada', 'Canadian'],
        'AU' => ['Australia', 'Australian'],
        'IN' => ['India', 'Indian'],
        'CN' => ['China', 'Chinese'],
    ];

    /**
     * Extract entities from query.
     */
    public function extract(string $query, string $intent): array
    {
        $entities = [];
        
        // Extract time period
        $entities['time_period'] = $this->extractTimePeriod($query);
        
        // Extract country
        $entities['country'] = $this->extractCountry($query);
        
        // Extract metric type
        $entities['metric'] = $this->extractMetric($query, $intent);
        
        // Extract period granularity for trends
        $entities['period_granularity'] = $this->extractPeriodGranularity($query);
        
        return array_filter($entities, fn($v) => $v !== null);
    }

    /**
     * Extract time period from query.
     */
    protected function extractTimePeriod(string $query): ?array
    {
        // Try predefined patterns first
        foreach ($this->timePatterns as $pattern => $dates) {
            if (stripos($query, $pattern) !== false) {
                return [
                    'pattern' => $pattern,
                    'start' => Carbon::parse($dates['start']),
                    'end' => Carbon::parse($dates['end']),
                    'label' => $dates['period'],
                ];
            }
        }
        
        // Try to extract explicit dates (YYYY-MM-DD format)
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $query, $matches)) {
            return [
                'pattern' => 'explicit',
                'start' => Carbon::parse($matches[1]),
                'end' => Carbon::now(),
                'label' => 'custom',
            ];
        }
        
        // Try month names (e.g., "January 2024", "Jan 2024")
        if (preg_match('/(january|february|march|april|may|june|july|august|september|october|november|december)\s+(\d{4})/i', $query, $matches)) {
            $month = $matches[1];
            $year = $matches[2];
            return [
                'pattern' => 'month_year',
                'start' => Carbon::parse("first day of {$month} {$year}"),
                'end' => Carbon::parse("last day of {$month} {$year}"),
                'label' => strtolower($month) . '_' . $year,
            ];
        }
        
        // Default to last 30 days if no period specified
        return [
            'pattern' => 'default',
            'start' => Carbon::now()->subDays(30),
            'end' => Carbon::now(),
            'label' => 'last_30_days',
        ];
    }

    /**
     * Extract country from query.
     */
    protected function extractCountry(string $query): ?string
    {
        foreach ($this->countryAliases as $code => $aliases) {
            foreach ($aliases as $alias) {
                if (stripos($query, $alias) !== false) {
                    return $code;
                }
            }
        }
        
        return null;
    }

    /**
     * Extract metric type from query.
     */
    protected function extractMetric(string $query, string $intent): ?string
    {
        if (stripos($query, 'revenue') !== false || stripos($query, 'money') !== false || stripos($query, 'income') !== false) {
            return 'revenue';
        }
        
        if (stripos($query, 'application') !== false || stripos($query, 'visitor') !== false) {
            return 'applications';
        }
        
        if (stripos($query, 'approval') !== false) {
            return 'approval_rate';
        }
        
        if (stripos($query, 'denial') !== false || stripos($query, 'rejection') !== false) {
            return 'denial_rate';
        }
        
        // Infer from intent
        return match($intent) {
            'revenue_total', 'revenue_breakdown', 'revenue_trend' => 'revenue',
            'visitor_count', 'visitor_breakdown' => 'applications',
            'approval_rate' => 'approval_rate',
            default => null,
        };
    }

    /**
     * Extract period granularity for trend queries.
     */
    protected function extractPeriodGranularity(string $query): ?string
    {
        if (stripos($query, 'daily') !== false || stripos($query, 'day by day') !== false) {
            return 'daily';
        }
        
        if (stripos($query, 'weekly') !== false || stripos($query, 'week by week') !== false) {
            return 'weekly';
        }
        
        if (stripos($query, 'monthly') !== false || stripos($query, 'month by month') !== false) {
            return 'monthly';
        }
        
        if (stripos($query, 'yearly') !== false || stripos($query, 'year by year') !== false) {
            return 'yearly';
        }
        
        return null;
    }
}
