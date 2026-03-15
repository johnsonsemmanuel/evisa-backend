<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * NORMALIZATION: Replace mfa_missions.covered_nationalities JSON array
     * with proper pivot table.
     */
    public function up(): void
    {
        Schema::create('mfa_mission_nationalities', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignId('mfa_mission_id')
                ->constrained('mfa_missions')
                ->cascadeOnDelete();
            
            $table->char('country_code', 2)->index(); // ISO 3166-1 alpha-2
            
            // Coverage details
            $table->date('coverage_start_date')->nullable();
            $table->date('coverage_end_date')->nullable();
            
            // Consular services offered
            $table->boolean('offers_visa_services')->default(true);
            $table->boolean('offers_passport_services')->default(false);
            $table->boolean('offers_notarial_services')->default(false);
            
            // Processing capacity
            $table->integer('monthly_visa_capacity')->nullable(); // Max applications per month
            $table->integer('average_processing_days')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->unique(['mfa_mission_id', 'country_code'], 'mission_nationality_unique');
            $table->index(['country_code', 'coverage_start_date', 'coverage_end_date'], 'idx_mission_coverage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mfa_mission_nationalities');
    }
};
