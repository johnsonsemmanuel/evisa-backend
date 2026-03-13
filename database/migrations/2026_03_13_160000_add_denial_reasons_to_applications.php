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
        Schema::table('applications', function (Blueprint $table) {
            // Add JSON field for multiple denial reason codes
            if (!Schema::hasColumn('applications', 'denial_reason_codes')) {
                $table->json('denial_reason_codes')->nullable()->after('decision_notes');
            }
            
            // Keep decision_notes for additional free-text explanation
            // denial_reason_codes will store array of reason code IDs
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            if (Schema::hasColumn('applications', 'denial_reason_codes')) {
                $table->dropColumn('denial_reason_codes');
            }
        });
    }
};
