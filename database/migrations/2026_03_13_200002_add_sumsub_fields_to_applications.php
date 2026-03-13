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
            $table->string('sumsub_applicant_id')->nullable()->after('reference_number');
            $table->enum('sumsub_verification_status', ['not_required', 'pending', 'completed', 'failed'])->default('not_required')->after('sumsub_applicant_id');
            $table->enum('sumsub_review_result', ['approved', 'rejected', 'pending', 'init'])->nullable()->after('sumsub_verification_status');
            $table->timestamp('sumsub_verified_at')->nullable()->after('sumsub_review_result');
            
            $table->index(['sumsub_applicant_id']);
            $table->index(['sumsub_verification_status']);
        });
        
        Schema::table('eta_applications', function (Blueprint $table) {
            $table->string('sumsub_applicant_id')->nullable()->after('eta_number');
            $table->enum('sumsub_verification_status', ['not_required', 'pending', 'completed', 'failed'])->default('not_required')->after('sumsub_applicant_id');
            $table->enum('sumsub_review_result', ['approved', 'rejected', 'pending', 'init'])->nullable()->after('sumsub_verification_status');
            $table->timestamp('sumsub_verified_at')->nullable()->after('sumsub_review_result');
            
            $table->index(['sumsub_applicant_id']);
            $table->index(['sumsub_verification_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex(['sumsub_applicant_id']);
            $table->dropIndex(['sumsub_verification_status']);
            $table->dropColumn([
                'sumsub_applicant_id',
                'sumsub_verification_status', 
                'sumsub_review_result',
                'sumsub_verified_at'
            ]);
        });
        
        Schema::table('eta_applications', function (Blueprint $table) {
            $table->dropIndex(['sumsub_applicant_id']);
            $table->dropIndex(['sumsub_verification_status']);
            $table->dropColumn([
                'sumsub_applicant_id',
                'sumsub_verification_status',
                'sumsub_review_result', 
                'sumsub_verified_at'
            ]);
        });
    }
};