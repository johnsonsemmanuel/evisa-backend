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
            if (!Schema::hasColumn('applications', 'processing_tier')) {
                $table->string('processing_tier')->nullable()->after('status');
            }
            if (!Schema::hasColumn('applications', 'risk_screening_status')) {
                $table->string('risk_screening_status')->nullable()->after('assigned_officer_id');
            }
            if (!Schema::hasColumn('applications', 'risk_screening_notes')) {
                $table->text('risk_screening_notes')->nullable()->after('risk_screening_status');
            }
            if (!Schema::hasColumn('applications', 'evisa_qr_code')) {
                $table->string('evisa_qr_code')->nullable()->after('evisa_file_path');
            }
        });

        // Migrate existing tier data
        DB::table('applications')->where('tier', 'tier_1')->update(['processing_tier' => 'fast_track']);
        DB::table('applications')->where('tier', 'tier_2')->update(['processing_tier' => 'regular']);

        // Update tier_rules table
        Schema::table('tier_rules', function (Blueprint $table) {
            if (!Schema::hasColumn('tier_rules', 'processing_tier')) {
                $table->string('processing_tier')->nullable()->after('tier');
            }
        });

        // Migrate tier_rules
        DB::table('tier_rules')->where('tier', 'tier_1')->update(['processing_tier' => 'fast_track']);
        DB::table('tier_rules')->where('tier', 'tier_2')->update(['processing_tier' => 'regular']);
    }

    public function down(): void
    {
        // FIXED: Properly reverse data migration
        
        // First, restore the original tier data if processing_tier exists
        if (Schema::hasColumn('applications', 'processing_tier')) {
            DB::table('applications')->where('processing_tier', 'fast_track')->update(['tier' => 'tier_1']);
            DB::table('applications')->where('processing_tier', 'regular')->update(['tier' => 'tier_2']);
        }

        if (Schema::hasColumn('tier_rules', 'processing_tier')) {
            DB::table('tier_rules')->where('processing_tier', 'fast_track')->update(['tier' => 'tier_1']);
            DB::table('tier_rules')->where('processing_tier', 'regular')->update(['tier' => 'tier_2']);
        }

        // Then drop the columns
        Schema::table('applications', function (Blueprint $table) {
            if (Schema::hasColumn('applications', 'processing_tier')) {
                $table->dropColumn('processing_tier');
            }
            if (Schema::hasColumn('applications', 'risk_screening_status')) {
                $table->dropColumn('risk_screening_status');
            }
            if (Schema::hasColumn('applications', 'risk_screening_notes')) {
                $table->dropColumn('risk_screening_notes');
            }
            if (Schema::hasColumn('applications', 'evisa_qr_code')) {
                $table->dropColumn('evisa_qr_code');
            }
        });

        Schema::table('tier_rules', function (Blueprint $table) {
            if (Schema::hasColumn('tier_rules', 'processing_tier')) {
                $table->dropColumn('processing_tier');
            }
        });
    }
};