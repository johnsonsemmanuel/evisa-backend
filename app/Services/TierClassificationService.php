<?php

namespace App\Services;

use App\Models\Application;
use App\Models\TierRule;

class TierClassificationService
{
    /**
     * Classify an application into Tier 1 or Tier 2 based on configured rules.
     * Returns the matching TierRule or null if no rule matches (defaults to Tier 1).
     */
    public function classify(Application $application): ?TierRule
    {
        $rules = TierRule::where('visa_type_id', $application->visa_type_id)
            ->where('is_active', true)
            ->orderBy('priority', 'desc')
            ->get();

        foreach ($rules as $rule) {
            if ($this->evaluateConditions($rule->conditions, $application)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Evaluate a set of JSON conditions against an application.
     *
     * Supported condition keys:
     *   - nationality_in: array of ISO country codes
     *   - nationality_not_in: array of ISO country codes
     *   - duration_gt: integer (days)
     *   - duration_lt: integer (days)
     *   - purpose_contains: string keyword
     */
    protected function evaluateConditions(array $conditions, Application $application): bool
    {
        foreach ($conditions as $key => $value) {
            switch ($key) {
                case 'nationality_in':
                    if (!in_array($application->nationality_encrypted, (array) $value)) {
                        return false;
                    }
                    break;

                case 'nationality_not_in':
                    if (in_array($application->nationality_encrypted, (array) $value)) {
                        return false;
                    }
                    break;

                case 'duration_gt':
                    if ($application->duration_days <= $value) {
                        return false;
                    }
                    break;

                case 'duration_lt':
                    if ($application->duration_days >= $value) {
                        return false;
                    }
                    break;

                case 'purpose_contains':
                    if (!str_contains(strtolower($application->purpose_of_visit ?? ''), strtolower($value))) {
                        return false;
                    }
                    break;
            }
        }

        return true;
    }
}
