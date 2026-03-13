<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class CleanSystemSeeder extends Seeder
{
    /**
     * Clean all users and create exactly 7 role-based accounts.
     * All passwords are set to: GhanaVisa2026!
     */
    public function run(): void
    {
        // Delete ALL existing users
        DB::table('users')->truncate();
        $this->command->info('🗑️  Deleted all existing users');

        $defaultPassword = Hash::make('GhanaVisa2026!');

        // ═══════════════════════════════════════════════════════════
        // 1. APPLICANT - Regular visa applicant
        // ═══════════════════════════════════════════════════════════
        User::create([
            'first_name' => 'John',
            'last_name' => 'Applicant',
            'email' => 'applicant@test.com',
            'password' => $defaultPassword,
            'role' => 'applicant',
            'agency' => null,
            'is_active' => true,
            'locale' => 'en',
            'email_verified_at' => now(),
        ]);

        // ═══════════════════════════════════════════════════════════
        // GIS OFFICERS (3 users)
        // ═══════════════════════════════════════════════════════════

        // 2. GIS Reviewer - Can only review and submit for approval
        User::create([
            'first_name' => 'Kwame',
            'last_name' => 'Mensah',
            'email' => 'gis.reviewer@evisa.gov.gh',
            'password' => $defaultPassword,
            'role' => 'gis_reviewer',
            'agency' => 'GIS',
            'can_review' => true,
            'can_approve' => false,
            'is_active' => true,
            'locale' => 'en',
            'email_verified_at' => now(),
        ]);

        // 3. GIS Approver - Can review AND approve
        User::create([
            'first_name' => 'Ama',
            'last_name' => 'Owusu',
            'email' => 'gis.approver@evisa.gov.gh',
            'password' => $defaultPassword,
            'role' => 'gis_approver',
            'agency' => 'GIS',
            'can_review' => true,
            'can_approve' => true,
            'is_active' => true,
            'locale' => 'en',
            'email_verified_at' => now(),
        ]);

        // 4. GIS Admin - Full access: review, approve, admin functions
        User::create([
            'first_name' => 'Kofi',
            'last_name' => 'Asante',
            'email' => 'gis.admin@evisa.gov.gh',
            'password' => $defaultPassword,
            'role' => 'gis_admin',
            'agency' => 'GIS',
            'can_review' => true,
            'can_approve' => true,
            'is_active' => true,
            'locale' => 'en',
            'email_verified_at' => now(),
        ]);

        // ═══════════════════════════════════════════════════════════
        // MFA OFFICERS (3 users)
        // ═══════════════════════════════════════════════════════════

        // 5. MFA Reviewer - Can only review and submit for approval
        User::create([
            'first_name' => 'Efua',
            'last_name' => 'Dankwa',
            'email' => 'mfa.reviewer@evisa.gov.gh',
            'password' => $defaultPassword,
            'role' => 'mfa_reviewer',
            'agency' => 'MFA',
            'can_review' => true,
            'can_approve' => false,
            'is_active' => true,
            'locale' => 'en',
            'email_verified_at' => now(),
        ]);

        // 6. MFA Approver - Can review AND approve
        User::create([
            'first_name' => 'Yaw',
            'last_name' => 'Boateng',
            'email' => 'mfa.approver@evisa.gov.gh',
            'password' => $defaultPassword,
            'role' => 'mfa_approver',
            'agency' => 'MFA',
            'can_review' => true,
            'can_approve' => true,
            'is_active' => true,
            'locale' => 'en',
            'email_verified_at' => now(),
        ]);

        // 7. MFA Admin - Full access: review, approve, admin, all missions
        User::create([
            'first_name' => 'Akua',
            'last_name' => 'Frimpong',
            'email' => 'mfa.admin@evisa.gov.gh',
            'password' => $defaultPassword,
            'role' => 'mfa_admin',
            'agency' => 'MFA',
            'can_review' => true,
            'can_approve' => true,
            'is_active' => true,
            'locale' => 'en',
            'email_verified_at' => now(),
        ]);

        // 8. SYSTEM ADMIN - Super admin for entire system
        User::create([
            'first_name' => 'System',
            'last_name' => 'Administrator',
            'email' => 'admin@evisa.gov.gh',
            'password' => $defaultPassword,
            'role' => 'admin',
            'agency' => 'ADMIN',
            'can_review' => true,
            'can_approve' => true,
            'is_active' => true,
            'locale' => 'en',
            'email_verified_at' => now(),
        ]);

        $this->command->info('');
        $this->command->info('✅ Created 8 user accounts:');
        $this->command->info('');
        $this->command->info('┌─────────────────────────────────────────────────────────────────┐');
        $this->command->info('│ APPLICANT                                                       │');
        $this->command->info('├─────────────────────────────────────────────────────────────────┤');
        $this->command->info('│ applicant@test.com          │ John Applicant    │ Applicant    │');
        $this->command->info('├─────────────────────────────────────────────────────────────────┤');
        $this->command->info('│ GIS OFFICERS                                                    │');
        $this->command->info('├─────────────────────────────────────────────────────────────────┤');
        $this->command->info('│ gis.reviewer@evisa.gov.gh   │ Kwame Mensah      │ Reviewer     │');
        $this->command->info('│ gis.approver@evisa.gov.gh   │ Ama Owusu         │ Approver     │');
        $this->command->info('│ gis.admin@evisa.gov.gh      │ Kofi Asante       │ Admin        │');
        $this->command->info('├─────────────────────────────────────────────────────────────────┤');
        $this->command->info('│ MFA OFFICERS                                                    │');
        $this->command->info('├─────────────────────────────────────────────────────────────────┤');
        $this->command->info('│ mfa.reviewer@evisa.gov.gh   │ Efua Dankwa       │ Reviewer     │');
        $this->command->info('│ mfa.approver@evisa.gov.gh   │ Yaw Boateng       │ Approver     │');
        $this->command->info('│ mfa.admin@evisa.gov.gh      │ Akua Frimpong     │ Admin        │');
        $this->command->info('├─────────────────────────────────────────────────────────────────┤');
        $this->command->info('│ SYSTEM ADMIN                                                    │');
        $this->command->info('├─────────────────────────────────────────────────────────────────┤');
        $this->command->info('│ admin@evisa.gov.gh          │ System Admin      │ Super Admin  │');
        $this->command->info('└─────────────────────────────────────────────────────────────────┘');
        $this->command->info('');
        $this->command->info('🔐 All accounts use password: GhanaVisa2026!');
    }
}
