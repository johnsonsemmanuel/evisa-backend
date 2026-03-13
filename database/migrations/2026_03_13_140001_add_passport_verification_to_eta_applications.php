<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('eta_applications', function (Blueprint $table) {
            // Add issuing authority field (required for passport verification)
            $table->string('issuing_authority', 200)->nullable()->after('passport_expiry_date');
            
            // Add passport verification status and data
            $table->string('passport_verification_status', 50)->nullable()->after('issuing_authority');
            $table->json('passport_verification_data')->nullable()->after('passport_verification_status');
            
            // Add indexes for performance
            $table->index('passport_verification_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eta_applications', function (Blueprint $table) {
            $table->dropIndex(['passport_verification_status']);
            $table->dropColumn([
                'issuing_authority',
                'passport_verification_status', 
                'passport_verification_data'
            ]);
        });
    }
};