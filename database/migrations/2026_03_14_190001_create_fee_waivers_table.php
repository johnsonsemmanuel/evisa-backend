<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Fee waivers for diplomatic, ECOWAS, and other special categories.
     * Allows flexible waiver rules without code changes.
     */
    public function up(): void
    {
        Schema::create('fee_waivers', function (Blueprint $table) {
            $table->id();
            
            // Waiver identification
            $table->string('name')->unique(); // e.g., 'ECOWAS_WAIVER', 'DIPLOMATIC_WAIVER'
            $table->text('description')->nullable();
            
            // Applicable nationalities (ISO 3166-1 alpha-2 codes)
            $table->json('nationality_codes'); // e.g., ["GH", "NG", "SN"] for ECOWAS
            
            // Applicable visa types (null = all visa types)
            $table->foreignId('visa_type_id')->nullable()->constrained('visa_types')->cascadeOnDelete();
            
            // Waiver type and value
            $table->enum('waiver_type', [
                'full',              // 100% waiver (free)
                'percentage',        // Percentage discount
                'fixed_reduction'    // Fixed amount reduction
            ])->default('full');
            
            // Waiver value interpretation:
            // - full: ignored (always 100%)
            // - percentage: 0-10000 (10000 = 100%, 5000 = 50%)
            // - fixed_reduction: amount in pesewas to subtract
            $table->unsignedBigInteger('waiver_value')->default(10000);
            
            // Activation and effective dates
            $table->boolean('is_active')->default(true);
            $table->date('effective_from');
            $table->date('effective_until')->nullable(); // Null = no expiry
            
            // Audit trail
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            // Indexes
            $table->index('is_active');
            $table->index(['effective_from', 'effective_until']);
            $table->index('visa_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_waivers');
    }
};
