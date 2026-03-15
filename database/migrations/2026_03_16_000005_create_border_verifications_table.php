<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * RELATIONSHIP 9: BorderVerification → Application (one-to-one)
     * 
     * Tracks border verification events for approved visa applications.
     * One verification record per application.
     */
    public function up(): void
    {
        Schema::create('border_verifications', function (Blueprint $table) {
            $table->id();
            
            // One-to-one relationship with applications
            $table->foreignId('application_id')
                ->unique()
                ->constrained('applications')
                ->cascadeOnDelete();
            
            // Border officer who performed verification
            $table->foreignId('verified_by')
                ->constrained('users')
                ->restrictOnDelete();
            
            // Verification timestamp
            $table->timestamp('verified_at')->useCurrent();
            
            // Verification method
            $table->enum('verification_method', ['qr_scan', 'manual', 'system', 'biometric'])->default('qr_scan');
            
            // Aeropass check (Interpol watchlist)
            $table->boolean('aeropass_checked')->default(false);
            $table->enum('aeropass_result', ['clear', 'hit', 'inconclusive'])->nullable();
            $table->text('aeropass_details')->nullable()->comment('Details if hit or inconclusive');
            
            // Entry decision
            $table->enum('entry_status', ['admitted', 'refused', 'further_examination', 'deferred'])->index();
            
            // Port of entry
            $table->string('port_of_entry')->comment('Airport/border post name');
            $table->string('port_code', 10)->nullable()->comment('IATA/ICAO code');
            
            // Additional verification details
            $table->boolean('biometric_verified')->default(false);
            $table->boolean('document_verified')->default(true);
            $table->text('notes')->nullable();
            
            // Refusal details (if entry_status = refused)
            $table->text('refusal_reason')->nullable();
            $table->string('refusal_code', 20)->nullable();
            
            // Duration of stay granted (may differ from application)
            $table->integer('granted_duration_days')->nullable();
            $table->date('authorized_until')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('verified_by');
            $table->index('verified_at');
            $table->index(['entry_status', 'verified_at']);
            $table->index('port_of_entry');
            $table->index(['aeropass_checked', 'aeropass_result']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('border_verifications');
    }
};
