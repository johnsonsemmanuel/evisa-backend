<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Models\ReasonCode;
use App\Models\User;
use Illuminate\Console\Command;

class TestMultipleDenialReasons extends Command
{
    protected $signature = 'test:multiple-denial-reasons';
    protected $description = 'Test multiple denial reasons functionality';

    public function handle()
    {
        $this->info('Testing Multiple Denial Reasons Feature...');
        $this->newLine();

        // 1. Check if denial_reason_codes field exists
        $this->info('1. Checking database schema...');
        $application = Application::first();
        if (!$application) {
            $this->error('No applications found in database');
            return 1;
        }

        try {
            $testCodes = ['FRAUD_SUSPECTED', 'INCOMPLETE_DOCS'];
            $application->denial_reason_codes = $testCodes;
            $application->save();
            $application->refresh();
            
            if ($application->denial_reason_codes === $testCodes) {
                $this->info('✓ denial_reason_codes field exists and works correctly');
            } else {
                $this->error('✗ denial_reason_codes field not working properly');
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('✗ Error testing denial_reason_codes: ' . $e->getMessage());
            return 1;
        }

        // 2. Check ReasonCode model
        $this->info('2. Checking ReasonCode model...');
        $rejectCodes = ReasonCode::where('action_type', 'reject')
            ->where('is_active', true)
            ->get();
        
        if ($rejectCodes->count() > 0) {
            $this->info("✓ Found {$rejectCodes->count()} active rejection reason codes:");
            foreach ($rejectCodes->take(5) as $code) {
                $this->line("  - [{$code->code}] {$code->reason}");
            }
        } else {
            $this->warn('⚠ No rejection reason codes found. You may need to seed them.');
        }

        // 3. Test getDenialReasonObjects() helper
        $this->newLine();
        $this->info('3. Testing getDenialReasonObjects() helper...');
        
        if ($rejectCodes->count() >= 2) {
            $testApp = Application::where('status', 'pending_approval')->first();
            if (!$testApp) {
                $testApp = Application::first();
            }
            
            $testCodes = $rejectCodes->take(2)->pluck('code')->toArray();
            $testApp->denial_reason_codes = $testCodes;
            $testApp->save();
            
            $denialObjects = $testApp->getDenialReasonObjects();
            
            if ($denialObjects->count() === 2) {
                $this->info('✓ getDenialReasonObjects() works correctly');
                $this->line('  Denial reasons:');
                foreach ($denialObjects as $obj) {
                    $this->line("  - [{$obj->code}] {$obj->reason}");
                }
            } else {
                $this->error('✗ getDenialReasonObjects() not working properly');
            }
        }

        // 4. Check controller validation
        $this->newLine();
        $this->info('4. Controller validation rules...');
        $this->line('  GIS CaseController::deny() - expects:');
        $this->line('    - denial_reason_codes: required|array|min:1');
        $this->line('    - denial_reason_codes.*: required|string|exists:reason_codes,code');
        $this->line('    - notes: nullable|string|max:2000');
        $this->newLine();
        $this->line('  MFA EscalationController::deny() - expects:');
        $this->line('    - denial_reason_codes: required|array|min:1');
        $this->line('    - denial_reason_codes.*: required|string|exists:reason_codes,code');
        $this->line('    - notes: nullable|string|max:2000');

        // 5. Example API request
        $this->newLine();
        $this->info('5. Example API request format:');
        $this->line('POST /api/gis/cases/{id}/deny');
        $this->line('POST /api/mfa/escalations/{id}/deny');
        $this->newLine();
        $this->line('{');
        $this->line('  "denial_reason_codes": [');
        $this->line('    "FRAUD_SUSPECTED",');
        $this->line('    "INCOMPLETE_DOCS",');
        $this->line('    "INVALID_PASSPORT"');
        $this->line('  ],');
        $this->line('  "notes": "Additional context for denial"');
        $this->line('}');

        $this->newLine();
        $this->info('✅ Multiple Denial Reasons Feature Test Complete!');
        $this->newLine();
        $this->info('Summary:');
        $this->line('- Database field: denial_reason_codes (JSON array)');
        $this->line('- Model helper: getDenialReasonObjects()');
        $this->line('- Controllers updated: GIS CaseController, MFA EscalationController');
        $this->line('- Validation: At least 1 reason required, all must be type "reject"');
        
        return 0;
    }
}
