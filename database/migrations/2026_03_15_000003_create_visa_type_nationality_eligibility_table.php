<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * NORMALIZATION: Replace visa_types.eligible_nationalities and 
     * visa_types.blacklisted_nationalities JSON arrays with proper pivot table.
     * 
     * Supports nuanced eligibility: eligible, restricted, blacklisted, exempt
     */
    public function up(): void
    {
        Schema::create('visa_type_nationality_eligibility', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignId('visa_type_id')
                ->constrained('visa_types')
                ->cascadeOnDelete();
            
            $table->char('country_code', 2)->index(); // ISO 3166-1 alpha-2
            
            // Eligibility type
            $table->enum('eligibility_type', [
                'eligible',      // Can apply normally
                'restricted',    // Can apply with additional requirements
                'blacklisted',   // Cannot apply
                'exempt'         // Visa-free entry (no application needed)
            ])->default('eligible');
            
            // Additional requirements for 'restricted' type
            $table->text('restriction_notes')->nullable(); // e.g., "Requires invitation letter from government agency"
            $table->boolean('requires_interview')->default(false);
            $table->boolean('requires_security_clearance')->default(false);
            
            // Effective dates
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            
            // Audit trail
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            
            // Indexes
            $table->unique(['visa_type_id', 'country_code'], 'visa_nationality_unique');
            $table->index(['country_code', 'eligibility_type'], 'idx_country_eligibility');
            $table->index(['eligibility_type', 'effective_from', 'effective_until'], 'idx_eligibility_dates');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visa_type_nationality_eligibility');
    }
};
