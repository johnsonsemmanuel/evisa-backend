<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class BorderVerificationUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating Border Verification System users...');

        // Immigration Officers
        $immigrationOfficers = [
            [
                'first_name' => 'Kwame',
                'last_name' => 'Asante',
                'email' => 'kwame.asante@immigration.gov.gh',
                'role' => 'immigration_officer',
                'agency' => 'Immigration Service',
                'location' => 'Kotoka International Airport',
            ],
            [
                'first_name' => 'Akosua',
                'last_name' => 'Mensah',
                'email' => 'akosua.mensah@immigration.gov.gh',
                'role' => 'immigration_officer',
                'agency' => 'Immigration Service',
                'location' => 'Tema Port',
            ],
            [
                'first_name' => 'Kofi',
                'last_name' => 'Boateng',
                'email' => 'kofi.boateng@immigration.gov.gh',
                'role' => 'immigration_officer',
                'agency' => 'Immigration Service',
                'location' => 'Aflao Border',
            ],
            [
                'first_name' => 'Ama',
                'last_name' => 'Osei',
                'email' => 'ama.osei@immigration.gov.gh',
                'role' => 'immigration_officer',
                'agency' => 'Immigration Service',
                'location' => 'Elubo Border',
            ],
            [
                'first_name' => 'Yaw',
                'last_name' => 'Adjei',
                'email' => 'yaw.adjei@immigration.gov.gh',
                'role' => 'immigration_officer',
                'agency' => 'Immigration Service',
                'location' => 'Kotoka International Airport',
            ],
        ];

        // Airline Staff
        $airlineStaff = [
            [
                'first_name' => 'Sarah',
                'last_name' => 'Johnson',
                'email' => 'sarah.johnson@delta.com',
                'role' => 'airline_staff',
                'agency' => 'Delta Air Lines',
                'airline' => 'Delta Air Lines',
            ],
            [
                'first_name' => 'Michael',
                'last_name' => 'Chen',
                'email' => 'michael.chen@emirates.com',
                'role' => 'airline_staff',
                'agency' => 'Emirates',
                'airline' => 'Emirates',
            ],
            [
                'first_name' => 'Fatima',
                'last_name' => 'Al-Rashid',
                'email' => 'fatima.alrashid@turkishairlines.com',
                'role' => 'airline_staff',
                'agency' => 'Turkish Airlines',
                'airline' => 'Turkish Airlines',
            ],
            [
                'first_name' => 'James',
                'last_name' => 'Wilson',
                'email' => 'james.wilson@britishairways.com',
                'role' => 'airline_staff',
                'agency' => 'British Airways',
                'airline' => 'British Airways',
            ],
            [
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'email' => 'maria.santos@klm.com',
                'role' => 'airline_staff',
                'agency' => 'KLM Royal Dutch Airlines',
                'airline' => 'KLM Royal Dutch Airlines',
            ],
            [
                'first_name' => 'David',
                'last_name' => 'Thompson',
                'email' => 'david.thompson@lufthansa.com',
                'role' => 'airline_staff',
                'agency' => 'Lufthansa',
                'airline' => 'Lufthansa',
            ],
        ];

        // System Administrators
        $administrators = [
            [
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'email' => 'admin@evisa.gov.gh',
                'role' => 'admin',
                'agency' => 'IT Department',
                'department' => 'IT Department',
            ],
            [
                'first_name' => 'Border Control',
                'last_name' => 'Supervisor',
                'email' => 'supervisor@immigration.gov.gh',
                'role' => 'admin',
                'agency' => 'Immigration Service',
                'department' => 'Immigration Service',
            ],
        ];

        // Test Users (for development/testing)
        $testUsers = [
            [
                'first_name' => 'Test Immigration',
                'last_name' => 'Officer',
                'email' => 'test.immigration@example.com',
                'role' => 'immigration_officer',
                'agency' => 'Test Agency',
                'location' => 'Test Location',
            ],
            [
                'first_name' => 'Test Airline',
                'last_name' => 'Staff',
                'email' => 'test.airline@example.com',
                'role' => 'airline_staff',
                'agency' => 'Test Airlines',
                'airline' => 'Test Airlines',
            ],
        ];

        $defaultPassword = Hash::make('BorderVerification2026!');

        // Create Immigration Officers
        foreach ($immigrationOfficers as $officer) {
            $user = User::updateOrCreate(
                ['email' => $officer['email']],
                [
                    'first_name' => $officer['first_name'],
                    'last_name' => $officer['last_name'],
                    'role' => $officer['role'],
                    'agency' => $officer['agency'],
                    'password' => $defaultPassword,
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $this->command->info("Created Immigration Officer: {$officer['first_name']} {$officer['last_name']} ({$officer['location']})");
        }

        // Create Airline Staff
        foreach ($airlineStaff as $staff) {
            $user = User::updateOrCreate(
                ['email' => $staff['email']],
                [
                    'first_name' => $staff['first_name'],
                    'last_name' => $staff['last_name'],
                    'role' => $staff['role'],
                    'agency' => $staff['agency'],
                    'password' => $defaultPassword,
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $this->command->info("Created Airline Staff: {$staff['first_name']} {$staff['last_name']} ({$staff['airline']})");
        }

        // Create Administrators
        foreach ($administrators as $admin) {
            $user = User::updateOrCreate(
                ['email' => $admin['email']],
                [
                    'first_name' => $admin['first_name'],
                    'last_name' => $admin['last_name'],
                    'role' => $admin['role'],
                    'agency' => $admin['agency'],
                    'password' => $defaultPassword,
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $this->command->info("Created Administrator: {$admin['first_name']} {$admin['last_name']} ({$admin['department']})");
        }

        // Create Test Users
        foreach ($testUsers as $testUser) {
            $user = User::updateOrCreate(
                ['email' => $testUser['email']],
                [
                    'first_name' => $testUser['first_name'],
                    'last_name' => $testUser['last_name'],
                    'role' => $testUser['role'],
                    'agency' => $testUser['agency'],
                    'password' => $defaultPassword,
                    'email_verified_at' => now(),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $this->command->info("Created Test User: {$testUser['first_name']} {$testUser['last_name']}");
        }

        $this->command->info('');
        $this->command->info('=== BORDER VERIFICATION USERS CREATED ===');
        $this->command->info('Default Password: BorderVerification2026!');
        $this->command->info('');
        $this->command->info('Immigration Officers: 5 users');
        $this->command->info('Airline Staff: 6 users');
        $this->command->info('Administrators: 2 users');
        $this->command->info('Test Users: 2 users');
        $this->command->info('');
        $this->command->info('Total Users Created: 15');
        $this->command->info('');
        $this->command->info('IMPORTANT: Change default passwords before production use!');
    }
}