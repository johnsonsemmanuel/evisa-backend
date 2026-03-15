<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class ValidateDatabaseConfig extends Command
{
    protected $signature = 'db:validate';
    protected $description = 'Validate that MySQL database is properly configured';

    public function handle()
    {
        $this->info('🔍 Validating Database Configuration...');
        $this->newLine();

        // Check if MySQL is configured
        $connection = Config::get('database.default');
        
        if ($connection !== 'mysql') {
            $this->error('❌ ERROR: Database connection is not set to MySQL!');
            $this->error("   Current connection: {$connection}");
            $this->newLine();
            $this->warn('This system requires MySQL. Please update your .env file:');
            $this->line('   DB_CONNECTION=mysql');
            $this->line('   DB_HOST=127.0.0.1');
            $this->line('   DB_PORT=3306');
            $this->line('   DB_DATABASE=evisa_system');
            $this->line('   DB_USERNAME=root');
            $this->line('   DB_PASSWORD=your_password');
            return 1;
        }

        $this->info('✓ Database connection is set to MySQL');

        // Test MySQL connection
        try {
            $databaseName = DB::connection()->getDatabaseName();
            $this->info("✓ Connected to MySQL database: {$databaseName}");
        } catch (\Exception $e) {
            $this->error('❌ ERROR: Cannot connect to MySQL database!');
            $this->error("   {$e->getMessage()}");
            $this->newLine();
            $this->warn('Please ensure:');
            $this->line('   1. MySQL is installed and running');
            $this->line('   2. Database credentials in .env are correct');
            $this->line('   3. Database exists: CREATE DATABASE evisa_system;');
            return 1;
        }

        // Check MySQL version
        try {
            $version = DB::select('SELECT VERSION() as version')[0]->version;
            $this->info("✓ MySQL version: {$version}");
        } catch (\Exception $e) {
            $this->warn("⚠ Could not determine MySQL version");
        }

        // Check if tables exist
        try {
            $tables = DB::select('SHOW TABLES');
            $tableCount = count($tables);
            
            if ($tableCount === 0) {
                $this->warn('⚠ No tables found in database');
                $this->line('   Run: php artisan migrate');
            } else {
                $this->info("✓ Database has {$tableCount} tables");
            }
        } catch (\Exception $e) {
            $this->warn("⚠ Could not check tables: {$e->getMessage()}");
        }

        // Check critical tables
        $criticalTables = ['users', 'applications', 'payments', 'reason_codes'];
        $missingTables = [];

        foreach ($criticalTables as $table) {
            try {
                DB::table($table)->limit(1)->count();
            } catch (\Exception $e) {
                $missingTables[] = $table;
            }
        }

        if (!empty($missingTables)) {
            $this->warn('⚠ Missing critical tables: ' . implode(', ', $missingTables));
            $this->line('   Run: php artisan migrate');
        } else {
            $this->info('✓ All critical tables exist');
        }

        $this->newLine();
        $this->info('========================================');
        $this->info('✅ Database configuration is valid!');
        $this->info('========================================');
        $this->newLine();

        return 0;
    }
}
