<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ReasonCode;

class ReasonCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $reasonCodes = [
            // Approval Reasons
            [
                'code' => 'AP001',
                'action_type' => 'approve',
                'reason' => 'Standard approval - all requirements met',
                'description' => 'Application meets all standard requirements for visa approval',
                'sort_order' => 1,
            ],
            [
                'code' => 'AP002',
                'action_type' => 'approve',
                'reason' => 'Expedited approval - urgent travel',
                'description' => 'Approved for urgent travel circumstances',
                'sort_order' => 2,
            ],
            [
                'code' => 'AP003',
                'action_type' => 'approve',
                'reason' => 'Diplomatic approval',
                'description' => 'Approved under diplomatic protocols',
                'sort_order' => 3,
            ],

            // Rejection Reasons
            [
                'code' => 'RJ001',
                'action_type' => 'reject',
                'reason' => 'Incomplete documentation',
                'description' => 'Required documents are missing or incomplete',
                'sort_order' => 1,
            ],
            [
                'code' => 'RJ002',
                'action_type' => 'reject',
                'reason' => 'Invalid passport',
                'description' => 'Passport is expired, damaged, or invalid',
                'sort_order' => 2,
            ],
            [
                'code' => 'RJ003',
                'action_type' => 'reject',
                'reason' => 'Insufficient funds proof',
                'description' => 'Unable to demonstrate sufficient financial resources',
                'sort_order' => 3,
            ],
            [
                'code' => 'RJ004',
                'action_type' => 'reject',
                'reason' => 'Security concerns',
                'description' => 'Application flagged for security review',
                'sort_order' => 4,
            ],
            [
                'code' => 'RJ005',
                'action_type' => 'reject',
                'reason' => 'Previous visa violations',
                'description' => 'History of visa violations or overstays',
                'sort_order' => 5,
            ],
            [
                'code' => 'RJ006',
                'action_type' => 'reject',
                'reason' => 'Fraudulent documents',
                'description' => 'Submitted documents appear to be fraudulent',
                'sort_order' => 6,
            ],
            [
                'code' => 'RJ007',
                'action_type' => 'reject',
                'reason' => 'Watchlist match',
                'description' => 'Applicant matches security watchlist',
                'sort_order' => 7,
            ],
            [
                'code' => 'RJ008',
                'action_type' => 'reject',
                'reason' => 'Purpose of visit unclear',
                'description' => 'Travel purpose is not clearly established',
                'sort_order' => 8,
            ],

            // Request Information Reasons
            [
                'code' => 'RI001',
                'action_type' => 'request_info',
                'reason' => 'Additional financial documents required',
                'description' => 'Need more proof of financial capacity',
                'sort_order' => 1,
            ],
            [
                'code' => 'RI002',
                'action_type' => 'request_info',
                'reason' => 'Travel itinerary clarification needed',
                'description' => 'Please provide detailed travel plans',
                'sort_order' => 2,
            ],
            [
                'code' => 'RI003',
                'action_type' => 'request_info',
                'reason' => 'Employment verification required',
                'description' => 'Need official employment confirmation',
                'sort_order' => 3,
            ],
            [
                'code' => 'RI004',
                'action_type' => 'request_info',
                'reason' => 'Accommodation details needed',
                'description' => 'Please provide accommodation booking confirmation',
                'sort_order' => 4,
            ],
            [
                'code' => 'RI005',
                'action_type' => 'request_info',
                'reason' => 'Document quality improvement needed',
                'description' => 'Please provide clearer document scans',
                'sort_order' => 5,
            ],
            [
                'code' => 'RI006',
                'action_type' => 'request_info',
                'reason' => 'Medical certificate required',
                'description' => 'Health certificate or vaccination proof needed',
                'sort_order' => 6,
            ],

            // Escalation Reasons
            [
                'code' => 'ES001',
                'action_type' => 'escalate',
                'reason' => 'Complex case requiring senior review',
                'description' => 'Case complexity requires MFA review',
                'sort_order' => 1,
            ],
            [
                'code' => 'ES002',
                'action_type' => 'escalate',
                'reason' => 'Security clearance required',
                'description' => 'Security assessment needed from MFA',
                'sort_order' => 2,
            ],
            [
                'code' => 'ES003',
                'action_type' => 'escalate',
                'reason' => 'Diplomatic status verification',
                'description' => 'Diplomatic credentials require MFA verification',
                'sort_order' => 3,
            ],
            [
                'code' => 'ES004',
                'action_type' => 'escalate',
                'reason' => 'Policy interpretation needed',
                'description' => 'Case requires policy clarification from MFA',
                'sort_order' => 4,
            ],

            // Border Control Reasons
            [
                'code' => 'BA001',
                'action_type' => 'border_admit',
                'reason' => 'Standard admission',
                'description' => 'Traveler admitted under standard procedures',
                'sort_order' => 1,
            ],
            [
                'code' => 'BA002',
                'action_type' => 'border_admit',
                'reason' => 'Diplomatic admission',
                'description' => 'Admitted under diplomatic protocols',
                'sort_order' => 2,
            ],
            [
                'code' => 'BD001',
                'action_type' => 'border_deny',
                'reason' => 'Invalid travel documents',
                'description' => 'Travel documents are invalid or expired',
                'sort_order' => 1,
            ],
            [
                'code' => 'BD002',
                'action_type' => 'border_deny',
                'reason' => 'Security concerns at border',
                'description' => 'Security issues identified at point of entry',
                'sort_order' => 2,
            ],
            [
                'code' => 'BS001',
                'action_type' => 'border_secondary',
                'reason' => 'Secondary screening required',
                'description' => 'Additional screening needed before admission',
                'sort_order' => 1,
            ],
            [
                'code' => 'BS002',
                'action_type' => 'border_secondary',
                'reason' => 'Document verification needed',
                'description' => 'Documents require additional verification',
                'sort_order' => 2,
            ],
        ];

        foreach ($reasonCodes as $reasonCode) {
            ReasonCode::create($reasonCode);
        }

        $this->command->info('Reason codes seeded successfully!');
    }
}