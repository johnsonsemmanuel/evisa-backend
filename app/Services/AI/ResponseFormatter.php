<?php

namespace App\Services\AI;

use Illuminate\Support\Collection;

class ResponseFormatter
{
    /**
     * Format response based on intent and data.
     */
    public function format(mixed $data, string $intent, array $entities): array
    {
        return match($intent) {
            'revenue_total' => $this->formatRevenueTotal($data, $entities),
            'revenue_breakdown' => $this->formatRevenueBreakdown($data, $entities),
            'revenue_trend' => $this->formatRevenueTrend($data, $entities),
            'visitor_count' => $this->formatVisitorCount($data, $entities),
            'visitor_breakdown' => $this->formatVisitorBreakdown($data, $entities),
            'approval_rate' => $this->formatApprovalRate($data, $entities),
            'demographics' => $this->formatDemographics($data, $entities),
            'comparison' => $this->formatComparison($data, $entities),
            'peak_analysis' => $this->formatPeakAnalysis($data, $entities),
            default => $this->formatGeneric($data),
        };
    }

    /**
     * Format revenue total response.
     */
    protected function formatRevenueTotal(float $total, array $entities): array
    {
        $period = $entities['time_period']['label'] ?? 'the selected period';
        
        return [
            'answer' => sprintf(
                "Total revenue for %s was **GHS %s**",
                $this->formatPeriodLabel($period),
                number_format($total, 2)
            ),
            'structured_data' => [
                'total' => $total,
                'currency' => 'GHS',
                'period' => $period,
            ],
            'visualization' => 'metric_card',
        ];
    }

    /**
     * Format revenue breakdown response.
     */
    protected function formatRevenueBreakdown(Collection $data, array $entities): array
    {
        $breakdownType = $this->inferBreakdownType($data);
        
        $table = $data->map(function ($item) {
            $category = $item['visa_type'] ?? $item['country'] ?? $item['tier'] ?? 'Unknown';
            return [
                'Category' => $category,
                'Revenue (GHS)' => number_format($item['total'], 2),
                'Count' => $item['count'],
                'Percentage' => isset($item['percentage']) ? $item['percentage'] . '%' : 'N/A',
            ];
        })->toArray();
        
        $answer = sprintf(
            "Here's the revenue breakdown by %s:\n\n%s",
            $breakdownType,
            $this->formatTable($table)
        );
        
        return [
            'answer' => $answer,
            'structured_data' => $data->toArray(),
            'visualization' => 'bar_chart',
        ];
    }

    /**
     * Format revenue trend response.
     */
    protected function formatRevenueTrend(Collection $data, array $entities): array
    {
        $period = $entities['period_granularity'] ?? 'daily';
        
        $answer = sprintf(
            "Revenue trend (%s):\n\n",
            $period
        );
        
        $table = $data->take(10)->map(function ($item) {
            return [
                'Period' => $item['period'],
                'Revenue (GHS)' => number_format($item['total'], 2),
                'Change' => isset($item['change_percentage']) ? $item['change_percentage'] . '%' : 'N/A',
            ];
        })->toArray();
        
        $answer .= $this->formatTable($table);
        
        if ($data->count() > 10) {
            $answer .= "\n\n_Showing first 10 periods. Total: {$data->count()} periods._";
        }
        
        return [
            'answer' => $answer,
            'structured_data' => $data->toArray(),
            'visualization' => 'line_chart',
        ];
    }

    /**
     * Format visitor count response.
     */
    protected function formatVisitorCount(int $count, array $entities): array
    {
        $period = $entities['time_period']['label'] ?? 'the selected period';
        
        return [
            'answer' => sprintf(
                "There were **%s applications** for %s",
                number_format($count),
                $this->formatPeriodLabel($period)
            ),
            'structured_data' => [
                'count' => $count,
                'period' => $period,
            ],
            'visualization' => 'metric_card',
        ];
    }

    /**
     * Format visitor breakdown response.
     */
    protected function formatVisitorBreakdown(Collection $data, array $entities): array
    {
        $table = $data->take(15)->map(function ($item) {
            return [
                'Country' => $item['country'],
                'Applications' => number_format($item['count']),
                'Percentage' => $item['percentage'] . '%',
            ];
        })->toArray();
        
        $answer = "Applications by country:\n\n" . $this->formatTable($table);
        
        if ($data->count() > 15) {
            $answer .= "\n\n_Showing top 15 countries. Total: {$data->count()} countries._";
        }
        
        return [
            'answer' => $answer,
            'structured_data' => $data->toArray(),
            'visualization' => 'bar_chart',
        ];
    }

    /**
     * Format approval rate response.
     */
    protected function formatApprovalRate(Collection $data, array $entities): array
    {
        $table = $data->take(15)->map(function ($item) {
            return [
                'Country' => $item['country'],
                'Total' => $item['total'],
                'Approved' => $item['approved'],
                'Denied' => $item['denied'],
                'Approval Rate' => $item['approval_rate'] . '%',
            ];
        })->toArray();
        
        $answer = "Approval rates by country:\n\n" . $this->formatTable($table);
        
        if ($data->count() > 15) {
            $answer .= "\n\n_Showing top 15 countries. Total: {$data->count()} countries._";
        }
        
        return [
            'answer' => $answer,
            'structured_data' => $data->toArray(),
            'visualization' => 'stacked_bar_chart',
        ];
    }

    /**
     * Format demographics response.
     */
    protected function formatDemographics(array $data, array $entities): array
    {
        $answer = "**Demographics Breakdown**\n\n";
        
        // Age groups
        $answer .= "**Age Groups:**\n";
        foreach ($data['age_groups'] as $group => $stats) {
            if ($stats['count'] > 0) {
                $answer .= sprintf("- %s: %d (%s%%)\n", $group, $stats['count'], $stats['percentage']);
            }
        }
        
        // Gender
        $answer .= "\n**Gender:**\n";
        foreach ($data['gender'] as $gender => $stats) {
            if ($stats['count'] > 0) {
                $answer .= sprintf("- %s: %d (%s%%)\n", ucfirst($gender), $stats['count'], $stats['percentage']);
            }
        }
        
        // Purpose of visit (top 5)
        if (!empty($data['purpose_of_visit'])) {
            $answer .= "\n**Top Purposes of Visit:**\n";
            $purposes = collect($data['purpose_of_visit'])->sortByDesc('count')->take(5);
            foreach ($purposes as $purpose => $stats) {
                $answer .= sprintf("- %s: %d (%s%%)\n", $purpose, $stats['count'], $stats['percentage']);
            }
        }
        
        return [
            'answer' => $answer,
            'structured_data' => $data,
            'visualization' => 'pie_charts',
        ];
    }

    /**
     * Format comparison response.
     */
    protected function formatComparison(array $data, array $entities): array
    {
        $answer = sprintf(
            "**Period Comparison**\n\n" .
            "Period 1 (%s to %s): GHS %s\n" .
            "Period 2 (%s to %s): GHS %s\n\n" .
            "Change: GHS %s (%s%%)\n" .
            "Trend: %s",
            $data['period1']['start'],
            $data['period1']['end'],
            number_format($data['period1']['revenue'], 2),
            $data['period2']['start'],
            $data['period2']['end'],
            number_format($data['period2']['revenue'], 2),
            number_format($data['change'], 2),
            $data['change_percentage'],
            ucfirst($data['trend'])
        );
        
        return [
            'answer' => $answer,
            'structured_data' => $data,
            'visualization' => 'comparison_chart',
        ];
    }

    /**
     * Format peak analysis response.
     */
    protected function formatPeakAnalysis(array $data, array $entities): array
    {
        $metric = $data['metric'];
        $valueKey = $metric === 'revenue' ? 'total' : 'count';
        
        $answer = sprintf(
            "**Peak Analysis for %s**\n\n" .
            "Highest: %s on %s\n" .
            "Lowest: %s on %s\n" .
            "Average: %s",
            ucfirst($metric),
            number_format($data['highest'][$valueKey] ?? 0, 2),
            $data['highest']['period'] ?? 'N/A',
            number_format($data['lowest'][$valueKey] ?? 0, 2),
            $data['lowest']['period'] ?? 'N/A',
            number_format($data['average'], 2)
        );
        
        return [
            'answer' => $answer,
            'structured_data' => $data,
            'visualization' => 'line_chart',
        ];
    }

    /**
     * Format generic response.
     */
    protected function formatGeneric(mixed $data): array
    {
        return [
            'answer' => json_encode($data, JSON_PRETTY_PRINT),
            'structured_data' => $data,
            'visualization' => 'raw',
        ];
    }

    /**
     * Format data as markdown table.
     */
    protected function formatTable(array $data): string
    {
        if (empty($data)) {
            return "No data available.";
        }
        
        $headers = array_keys($data[0]);
        $rows = [];
        
        // Header row
        $rows[] = '| ' . implode(' | ', $headers) . ' |';
        $rows[] = '|' . str_repeat(' --- |', count($headers));
        
        // Data rows
        foreach ($data as $row) {
            $rows[] = '| ' . implode(' | ', array_values($row)) . ' |';
        }
        
        return implode("\n", $rows);
    }

    /**
     * Format period label for human readability.
     */
    protected function formatPeriodLabel(string $period): string
    {
        return str_replace('_', ' ', $period);
    }

    /**
     * Infer breakdown type from data structure.
     */
    protected function inferBreakdownType(Collection $data): string
    {
        if ($data->isEmpty()) {
            return 'category';
        }
        
        $first = $data->first();
        
        if (isset($first['visa_type'])) return 'visa type';
        if (isset($first['country'])) return 'country';
        if (isset($first['tier'])) return 'processing tier';
        
        return 'category';
    }
}
