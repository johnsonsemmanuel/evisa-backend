<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds TOTP-based Multi-Factor Authentication columns to users table.
     * Required by NIST SP 800-53 IA-2 for government systems.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // TOTP secret (encrypted) - generated during 2FA setup
            $table->text('two_factor_secret')->nullable()->after('password');
            
            // Confirmation timestamp - null means 2FA not yet set up
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
            
            // Recovery codes (encrypted) - JSON array of 8 backup codes
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_confirmed_at');
            
            // Whether 2FA is required for this user (true for officers/admins)
            $table->boolean('two_factor_required')->default(false)->after('two_factor_recovery_codes');
            
            // Index for querying users who need 2FA setup
            $table->index(['two_factor_required', 'two_factor_confirmed_at'], 'idx_2fa_required_confirmed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_2fa_required_confirmed');
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_confirmed_at',
                'two_factor_recovery_codes',
                'two_factor_required',
            ]);
        });
    }
};
