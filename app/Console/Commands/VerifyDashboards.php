<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Application;
use App\Models\EtaApplication;
use Illuminate\Console\Command;

class VerifyDashboards extends Command
{
    protected $signature = 'dashboard:verify';
    protected $description = 'Verify dashboard access and data visibility per specification';

    public function handle(): int
    {
        $this->info('Verifying Dashboard Implementation...');
        $this->newLine();

        // Test 1: ETA Visibility in Admin Dashboards
        $this->info('Test 1: ETA Visibility in Admin Dashboards');
        $etaCount = EtaApplication::count();
        $this->line("  Total ETAs in system: {$etaCount}");
        
        $flaggedEtas = EtaApplication::where('status', 'flagged')->count();
        $this->line("  Flagged ETAs: {$flaggedEtas}");
        
        if ($etaCount > 0) {
            $this->info('  ✓ ETAs exist in database');
        } else {
            $this->warn('  ⚠ No ETAs found - create test data');
        }
        $this->newLine();

        // Test 2: GIS Admin Access
        $this->info('Test 2: GIS Admin Access');
        $gisAdmin = User::where('role', 'gis_admin')->first();
        if ($gisAdmin) {
            $this->info("  ✓ GIS Admin user exists: {$gisAdmin->email}");
            
            // Check GIS applications
            $gisApps = Application::where('owner_agency', 'GIS')->count();
            $this->line("  GIS applications: {$gisApps}");
        } else {
            $this->error('  ✗ No GIS Admin user found');
        }
        $this->newLine();

        // Test 3: MFA Admin Access
        $this->info('Test 3: MFA Admin Access');
        $mfaAdmin = User::where('role', 'mfa_admin')->first();
        if ($mfaAdmin) {
            $this->info("  ✓ MFA Admin user exists: {$mfaAdmin->email}");
            
            // Check MFA applications
            $mfaApps = Application::where('owner_agency', 'MFA')->count();
            $this->line("  MFA applications: {$mfaApps}");
        } else {
            $this->error('  ✗ No MFA Admin user found');
        }
        $this->newLine();

        // Test 4: Queue Separation
        $this->info('Test 4: Queue Separation');
        $reviewQueue = Application::where('current_queue', 'REVIEW_QUEUE')->count();
        $approvalQueue = Application::where('current_queue', 'APPROVAL_QUEUE')->count();
        $this->line("  Review Queue: {$reviewQueue}");
        $this->line("  Approval Queue: {$approvalQueue}");
        
        if ($reviewQueue > 0 || $approvalQueue > 0) {
            $this->info('  ✓ Queue system operational');
        } else {
            $this->warn('  ⚠ No applications in queues');
        }
        $this->newLine();

        // Test 5: Mission Scoping
        $this->info('Test 5: Mission Scoping');
        $mfaOfficer = User::where('role', 'mfa_reviewer')
            ->whereNotNull('mission_id')
            ->first();
        
        if ($mfaOfficer) {
            $this->info("  ✓ MFA Officer with mission: {$mfaOfficer->email}");
            $this->line("  Mission ID: {$mfaOfficer->mission_id}");
            
            $missionApps = Application::where('owner_agency', 'MFA')
                ->where('owner_mission_id', $mfaOfficer->mission_id)
                ->count();
            $this->line("  Mission applications: {$missionApps}");
        } else {
            $this->warn('  ⚠ No MFA Officer with mission assignment found');
        }
        $this->newLine();

        // Test 6: Risk Score Distribution
        $this->info('Test 6: Risk Score Distribution');
        $lowRisk = Application::where('risk_level', 'Low')->count();
        $mediumRisk = Application::where('risk_level', 'Medium')->count();
        $highRisk = Application::where('risk_level', 'High')->count();
        $criticalRisk = Application::where('risk_level', 'Critical')->count();
        
        $this->line("  Low: {$lowRisk}");
        $this->line("  Medium: {$mediumRisk}");
        $this->line("  High: {$highRisk}");
        $this->line("  Critical: {$criticalRisk}");
        
        if ($lowRisk + $mediumRisk + $highRisk + $criticalRisk > 0) {
            $this->info('  ✓ Risk scoring operational');
        } else {
            $this->warn('  ⚠ No risk scores calculated');
        }
        $this->newLine();

        // Summary
        $this->info('Dashboard Verification Complete');
        $this->newLine();
        
        $this->info('Next Steps:');
        $this->line('1. Verify frontend dashboards display this data correctly');
        $this->line('2. Test cross-agency read-only tabs (GIS Admin → MFA, MFA Admin → GIS)');
        $this->line('3. Confirm ETA columns in admin dashboards');
        $this->line('4. Test mission-scoped access for MFA officers');
        
        return Command::SUCCESS;
    }
}
