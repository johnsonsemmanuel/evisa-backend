<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Payment reconciliation issues table for tracking discrepancies
     * between local payment records and gateway records.
     */
    public function up(): void
    {
        Schema::create('payment_reconciliation_issues', function (Blueprint $table) {
            $table->id();
            
            // Payment reference (nullable for MISSING_LOCAL issues)
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            
            // Gateway information
            $table->string('gateway'); // gcb, paystack
            $table->string('gateway_reference')->nullable(); // Gateway transaction ID
            
            // Issue classification
            $table->enum('issue_type', [
                'LOCAL_PAID_GATEWAY_FAILED',    // CRITICAL: We marked paid but gateway says failed
                'LOCAL_FAILED_GATEWAY_PAID',    // HIGH: We marked failed but gateway says paid
                'MISSING_LOCAL',                // CRITICAL: Gateway has transaction we don't have
                'AMOUNT_MISMATCH',              // CRITICAL: Amounts differ by more than 1 pesewa
                'REFERENCE_NOT_FOUND',          // HIGH: Our reference doesn't exist at gateway
            ]);
            
            // Status comparison
            $table->string('local_status')->nullable();   // Our payment status
            $table->string('gateway_status')->nullable(); // Gateway payment status
            
            // Amount comparison (in pesewas)
            $table->unsignedBigInteger('local_amount')->nullable();
            $table->unsignedBigInteger('gateway_amount')->nullable();
            
            // Resolution tracking
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            
            // Additional context
            $table->json('gateway_data')->nullable(); // Raw gateway response
            $table->date('reconciliation_date'); // Date this reconciliation was for
            
            $table->timestamps();
            
            // Indexes for efficient queries
            $table->index(['gateway', 'issue_type']);
            $table->index(['resolved_at', 'issue_type']);
            $table->index('reconciliation_date');
            $table->index(['payment_id', 'gateway']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliation_issues');
    }
};