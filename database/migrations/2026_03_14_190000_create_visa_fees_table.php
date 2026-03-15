<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Database-driven fee engine for government eVisa platform.
     * Fees are set by Ministry directive and can be updated without code deployment.
     */
    public function up(): void
    {
        Schema::create('visa_fees', function (Blueprint $table) {
            $table->id();
            
            // Fee identification
            $table->foreignId('visa_type_id')->constrained('visa_types')->cascadeOnDelete();
            
            // Nationality-based fee categories
            $table->enum('nationality_category', [
                'ecowas',       // ECOWAS member states
                'african',      // Other African nations
                'commonwealth', // Commonwealth countries
                'other',        // Rest of world
                'all'           // Applies to all nationalities
            ])->default('all');
            
            // Processing tier
            $table->enum('processing_tier', [
                'standard',     // 3-5 business days
                'express',      // Within 48 hours
                'emergency'     // Within 5 hours
            ])->default('standard');
            
            // Fee amount (stored in pesewas/cents for precision)
            $table->unsignedBigInteger('amount'); // e.g., 50000 = GHS 500.00
            $table->char('currency', 3)->default('GHS'); // ISO 4217 currency code
            
            // Activation and effective dates
            $table->boolean('is_active')->default(true);
            $table->date('effective_from'); // When this fee becomes active
            $table->date('effective_until')->nullable(); // Null = currently active
            
            // Audit trail
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            // Indexes for fast lookups
            $table->index(['visa_type_id', 'processing_tier', 'is_active']);
            $table->index(['nationality_category', 'is_active']);
            $table->index(['effective_from', 'effective_until']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visa_fees');
    }
};
