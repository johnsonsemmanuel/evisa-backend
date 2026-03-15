<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sumsub_applicant_id', 100)->nullable()->after('remember_token');
            $table->string('kyc_status', 32)->default('not_started')->after('sumsub_applicant_id');
            $table->timestamp('kyc_completed_at')->nullable()->after('kyc_status');
            $table->json('kyc_rejection_labels')->nullable()->after('kyc_completed_at');
            $table->string('kyc_level', 64)->nullable()->after('kyc_rejection_labels');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('sumsub_applicant_id');
            $table->index('kyc_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['kyc_status']);
            $table->dropIndex(['sumsub_applicant_id']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'sumsub_applicant_id',
                'kyc_status',
                'kyc_completed_at',
                'kyc_rejection_labels',
                'kyc_level',
            ]);
        });
    }
};
