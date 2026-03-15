<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * NORMALIZATION: Replace applications.denial_reason_codes JSON array
     * with proper many-to-many pivot table.
     */
    public function up(): void
    {
        Schema::create('application_denial_reasons', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignId('application_id')
                ->constrained('applications')
                ->cascadeOnDelete();
            
            $table->foreignId('reason_code_id')
                ->constrained('reason_codes')
                ->cascadeOnDelete();
            
            // Audit trail
            $table->foreignId('added_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->timestamps();
            
            // Indexes
            $table->unique(['application_id', 'reason_code_id'], 'app_reason_unique');
            $table->index('reason_code_id');
            $table->index('added_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_denial_reasons');
    }
};
