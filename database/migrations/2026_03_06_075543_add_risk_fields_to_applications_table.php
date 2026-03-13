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
            $table->integer('risk_score')->nullable()->after('risk_screening_status');
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->nullable()->after('risk_score');
            $table->boolean('watchlist_flagged')->default(false)->after('risk_level');
            $table->timestamp('risk_assessed_at')->nullable()->after('watchlist_flagged');
            $table->foreignId('service_tier_id')->nullable()->after('visa_type_id')->constrained('service_tiers')->nullOnDelete();
            $table->decimal('total_fee', 10, 2)->nullable()->after('service_tier_id');
            $table->decimal('government_fee', 10, 2)->nullable()->after('total_fee');
            $table->decimal('platform_fee', 10, 2)->nullable()->after('government_fee');
            $table->decimal('processing_fee', 10, 2)->nullable()->after('platform_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['service_tier_id']);
            $table->dropColumn([
                'risk_score',
                'risk_level',
                'watchlist_flagged',
                'risk_assessed_at',
                'service_tier_id',
                'total_fee',
                'government_fee',
                'platform_fee',
                'processing_fee',
            ]);
        });
    }
};
