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
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->enum('gateway', ['gcb', 'paystack'])->comment('Payment gateway that sent the webhook');
            $table->string('event_id')->comment('Unique event identifier from gateway (e.g., transaction reference)');
            $table->string('event_type')->comment('Type of event (e.g., charge.success, payment.confirmed)');
            $table->json('payload')->nullable()->comment('Full webhook payload for audit trail');
            $table->timestamp('processed_at')->nullable()->comment('When the webhook was successfully processed');
            $table->timestamps();

            // CRITICAL: Unique constraint prevents duplicate processing
            $table->unique(['gateway', 'event_id'], 'webhook_events_gateway_event_unique');
            
            // Index for querying by gateway and processing status
            $table->index(['gateway', 'processed_at'], 'webhook_events_gateway_processed_idx');
            
            // Index for cleanup queries (find old processed events)
            $table->index('created_at', 'webhook_events_created_at_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
