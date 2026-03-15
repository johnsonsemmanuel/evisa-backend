<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            VisaTypesSeeder::class,      // Seed visa types first
            ServiceTierSeeder::class,
            MfaMissionSeeder::class,
            RoutingRuleSeeder::class,
            Phase1Seeder::class,         // Creates tier rules for visa types
            BorderVerificationUsersSeeder::class,  // Creates border verification users
            ReasonCodeSeeder::class,     // Creates reason codes
        ]);

        $this->command->newLine();
        $this->command->info('✅ Database seeding completed successfully!');
        $this->command->line('   Run `php artisan users:list` to see all users');
    }
}

