<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * NORMALIZATION: Replace applications.risk_reasons JSON array
     * with proper table for risk factor tracking.
     */
    public function up(): void
    {
        Schema::create('application_risk_factors', function (Blueprint $table) {
            $table->id();
            
            // Foreign key
            $table->foreignId('application_id')
                ->constrained('applications')
                ->cascadeOnDelete();
            
            // Risk factor details
            $table->string('factor_code', 100)->index(); // e.g., 'passport_expiry_soon', 'high_risk_nationality'
            $table->string('factor_name'); // Human-readable name
            $table->text('factor_description')->nullable(); // Detailed explanation
            
            // Severity and scoring
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->integer('score_impact')->default(0); // How much this factor contributed to risk score
            
            // Detection metadata
            $table->timestamp('detected_at')->useCurrent();
            $table->string('detected_by')->default('system'); // 'system' or user_id
            
            // Resolution tracking
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['application_id', 'factor_code']);
            $table->index(['factor_code', 'severity']);
            $table->index('detected_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_risk_factors');
    }
};
