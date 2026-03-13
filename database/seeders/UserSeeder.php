<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. GIS Officer
        User::firstOrCreate(
            ['email' => 'officer@gis.gov.gh'],
            [
                'first_name' => 'GIS',
                'last_name' => 'Officer',
                'password' => Hash::make('password123'),
                'role' => 'gis_officer',
                'is_active' => true,
            ]
        );

        // 2. MFA Reviewer
        User::firstOrCreate(
            ['email' => 'reviewer@mfa.gov.gh'],
            [
                'first_name' => 'MFA',
                'last_name' => 'Reviewer',
                'password' => Hash::make('password123'),
                'role' => 'mfa_reviewer',
                'is_active' => true,
            ]
        );

        // 3. Admin
        User::firstOrCreate(
            ['email' => 'admin@gis.gov.gh'],
            [
                'first_name' => 'System',
                'last_name' => 'Admin',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        // 4. Applicant
        User::firstOrCreate(
            ['email' => 'applicant@example.com'],
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'password' => Hash::make('password123'),
                'role' => 'applicant',
                'is_active' => true,
            ]
        );

        // 5. Border Officer (Verifier)
        User::firstOrCreate(
            ['email' => 'border.officer@gis.gov.gh'],
            [
                'first_name' => 'Border',
                'last_name' => 'Officer',
                'password' => Hash::make('password123'),
                'role' => 'border_officer',
                'is_active' => true,
            ]
        );

        // 6. Border Supervisor
        User::firstOrCreate(
            ['email' => 'border.supervisor@gis.gov.gh'],
            [
                'first_name' => 'Border',
                'last_name' => 'Supervisor',
                'password' => Hash::make('password123'),
                'role' => 'border_supervisor',
                'is_active' => true,
            ]
        );

        // 7. Immigration Intelligence Desk
        User::firstOrCreate(
            ['email' => 'intelligence@gis.gov.gh'],
            [
                'first_name' => 'Intelligence',
                'last_name' => 'Officer',
                'password' => Hash::make('password123'),
                'role' => 'intelligence_officer',
                'is_active' => true,
            ]
        );
    }
}
