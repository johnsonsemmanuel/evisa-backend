<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * NORMALIZATION: Replace mfa_missions.visa_types_handled JSON array
     * with proper pivot table.
     */
    public function up(): void
    {
        Schema::create('mfa_mission_visa_types', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignId('mfa_mission_id')
                ->constrained('mfa_missions')
                ->cascadeOnDelete();
            
            $table->foreignId('visa_type_id')
                ->constrained('visa_types')
                ->cascadeOnDelete();
            
            // Processing capacity and performance
            $table->integer('monthly_capacity')->nullable(); // Max applications per month
            $table->integer('average_processing_days')->nullable();
            $table->integer('current_backlog')->default(0); // Current pending applications
            
            // Service availability
            $table->boolean('is_accepting_applications')->default(true);
            $table->date('service_start_date')->nullable();
            $table->date('service_end_date')->nullable();
            
            // Processing requirements
            $table->boolean('requires_interview')->default(false);
            $table->boolean('requires_biometrics')->default(false);
            $table->integer('override_sla_hours')->nullable(); // Override default SLA
            
            $table->timestamps();
            
            // Indexes
            $table->unique(['mfa_mission_id', 'visa_type_id'], 'mission_visa_type_unique');
            $table->index(['visa_type_id', 'is_accepting_applications'], 'idx_visa_accepting');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mfa_mission_visa_types');
    }
};
