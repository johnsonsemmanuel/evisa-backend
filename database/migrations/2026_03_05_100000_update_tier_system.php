<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For SQLite, we need to recreate the column
        // First, add a temporary column
        Schema::table('applications', function (Blueprint $table) {
            $table->string('processing_tier')->nullable()->after('status');
            $table->string('risk_screening_status')->nullable()->after('assigned_officer_id');
            $table->text('risk_screening_notes')->nullable()->after('risk_screening_status');
            $table->string('evisa_qr_code')->nullable()->after('evisa_file_path');
        });

        // Migrate existing tier data
        DB::table('applications')->where('tier', 'tier_1')->update(['processing_tier' => 'fast_track']);
        DB::table('applications')->where('tier', 'tier_2')->update(['processing_tier' => 'regular']);

        // Update tier_rules table
        Schema::table('tier_rules', function (Blueprint $table) {
            $table->string('processing_tier')->nullable()->after('tier');
        });

        // Migrate tier_rules
        DB::table('tier_rules')->where('tier', 'tier_1')->update(['processing_tier' => 'fast_track']);
        DB::table('tier_rules')->where('tier', 'tier_2')->update(['processing_tier' => 'regular']);
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['processing_tier', 'risk_screening_status', 'risk_screening_notes', 'evisa_qr_code']);
        });

        Schema::table('tier_rules', function (Blueprint $table) {
            $table->dropColumn('processing_tier');
        });
    }
};
