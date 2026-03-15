<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * RELATIONSHIP 5: Application → RiskScore (one-to-many with history)
     * 
     * This table stores HISTORY of risk scores. Aeropass re-checks may produce new scores.
     * Only one record per application can have is_current=true.
     */
    public function up(): void
    {
        Schema::create('risk_scores', function (Blueprint $table) {
            $table->id();
            
            // Foreign key to application
            $table->foreignId('application_id')
                ->constrained('applications')
                ->cascadeOnDelete();
            
            // Risk score and level
            $table->unsignedTinyInteger('score')->default(0)->comment('0-100 risk score');
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('low');
            
            // Scoring engine that produced this score
            $table->enum('scoring_engine', ['aeropass', 'manual', 'ml_model', 'system'])->default('system');
            
            // Risk factors that drove the score (JSON array)
            $table->json('factors')->nullable()->comment('Array of risk factor codes and weights');
            
            // Who assessed (null for automated scoring)
            $table->foreignId('assessed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            // When assessed
            $table->timestamp('assessed_at')->useCurrent();
            
            // Current flag - only ONE record per application can be true
            $table->boolean('is_current')->default(false)->index();
            
            // Additional context
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['application_id', 'is_current']);
            $table->index(['application_id', 'assessed_at']);
            $table->index(['risk_level', 'is_current']);
            $table->index('scoring_engine');
            
            // Unique constraint: only one current score per application
            $table->unique(['application_id', 'is_current'], 'unique_current_score')
                ->where('is_current', true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('risk_scores');
    }
};
