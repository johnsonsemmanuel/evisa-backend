<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * NORMALIZATION: Replace fee_waivers.nationality_codes JSON array
     * with proper pivot table.
     */
    public function up(): void
    {
        Schema::create('fee_waiver_nationalities', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignId('fee_waiver_id')
                ->constrained('fee_waivers')
                ->cascadeOnDelete();
            
            $table->char('country_code', 2)->index(); // ISO 3166-1 alpha-2
            
            // Per-nationality waiver override (optional)
            // If NULL, use fee_waiver.waiver_type and waiver_value
            $table->enum('override_waiver_type', ['full', 'percentage', 'fixed_reduction'])->nullable();
            $table->unsignedBigInteger('override_waiver_value')->nullable();
            
            // Effective dates (can differ from parent fee_waiver)
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->unique(['fee_waiver_id', 'country_code'], 'waiver_nationality_unique');
            $table->index(['country_code', 'effective_from', 'effective_until'], 'idx_waiver_dates');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_waiver_nationalities');
    }
};
