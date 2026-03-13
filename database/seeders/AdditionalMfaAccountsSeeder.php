<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdditionalMfaAccountsSeeder extends Seeder
{
    /**
     * Add additional MFA accounts for testing
     */
    public function run(): void
    {
        $defaultPassword = Hash::make('GhanaVisa2026!');

        // Efua Dankwa - MFA Approval Officer
        User::create([
            'first_name' => 'Efua',
            'last_name' => 'Dankwa',
            'email' => 'edankwa@mfa.gov.gh',
            'password' => $defaultPassword,
            'role' => 'mfa_approver',
            'agency' => 'MFA',
            'can_approve' => true,
            'is_active' => true,
            'locale' => 'en',
            'email_verified_at' => now(),
        ]);

        $this->command->info('✅ Created additional MFA account: edankwa@mfa.gov.gh');
        $this->command->info('📧 Password: GhanaVisa2026!');
    }
}
