<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Payment audit logs are separate from general audit logs because:
     * 1. Different retention requirements (financial records must be kept longer)
     * 2. Different access controls (only finance officers can view)
     * 3. Immutable - no updates or soft deletes allowed
     */
    public function up(): void
    {
        Schema::create('payment_audit_logs', function (Blueprint $table) {
            $table->id();
            
            // Event identification
            $table->string('event_type'); // payment_initiated, payment_status_changed, payment_amount_changed, etc.
            
            // Foreign keys
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete(); // Denormalized for fast lookup
            
            // Actor information
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete(); // Null for system/gateway events
            $table->enum('actor_type', ['user', 'system', 'gateway'])->default('system');
            
            // Change tracking
            $table->json('old_value')->nullable(); // Previous state
            $table->json('new_value')->nullable(); // New state
            
            // Context
            $table->string('ip_address', 45)->nullable(); // IPv4 or IPv6
            $table->text('user_agent')->nullable();
            $table->text('notes')->nullable(); // Additional context or warnings
            
            // Immutable timestamp - NO updated_at
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes for fast queries
            $table->index('payment_id');
            $table->index('application_id');
            $table->index('event_type');
            $table->index('actor_id');
            $table->index('created_at');
            $table->index(['payment_id', 'created_at']); // Composite for timeline queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_audit_logs');
    }
};
