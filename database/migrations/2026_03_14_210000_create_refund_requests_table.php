<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('refund_requests', function (Blueprint $table) {
            $table->id();
            
            // Payment and application references
            $table->foreignId('payment_id')->constrained('payments')->onDelete('cascade');
            $table->foreignId('application_id')->constrained('applications')->onDelete('cascade');
            
            // Gateway information
            $table->enum('gateway', ['gcb', 'paystack']);
            $table->string('gateway_refund_reference')->nullable();
            
            // Refund amount (in pesewas)
            $table->unsignedBigInteger('amount');
            
            // Refund reason and documentation
            $table->text('reason');
            $table->json('attachments')->nullable(); // Array of file IDs
            
            // Status tracking
            $table->enum('status', [
                'pending_approval',
                'awaiting_second_approval',
                'approved',
                'processing',
                'processed',
                'failed',
                'rejected'
            ])->default('pending_approval');
            
            // User tracking
            $table->foreignId('initiated_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->foreignId('rejected_by')->nullable()->constrained('users')->onDelete('restrict');
            
            // Timestamp tracking
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            
            // Gateway response
            $table->json('gateway_response')->nullable();
            $table->text('rejection_reason')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index('payment_id');
            $table->index('application_id');
            $table->index('status');
            $table->index('gateway');
            $table->index('initiated_by');
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refund_requests');
    }
};
