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
        Schema::create('eta_applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            
            // Applicant Information
            $table->string('first_name_encrypted');
            $table->string('last_name_encrypted');
            $table->date('date_of_birth');
            $table->string('gender')->nullable();
            $table->string('nationality_encrypted');
            
            // Passport Information
            $table->string('passport_number_encrypted');
            $table->date('passport_issue_date')->nullable();
            $table->date('passport_expiry_date');
            $table->string('passport_scan_path')->nullable();
            $table->string('photo_path')->nullable();
            
            // Contact Details
            $table->string('email_encrypted');
            $table->string('phone_encrypted')->nullable();
            $table->text('residential_address_encrypted')->nullable();
            
            // Travel Information
            $table->date('intended_arrival_date');
            $table->string('port_of_entry')->nullable();
            $table->string('airline')->nullable();
            $table->string('flight_number')->nullable();
            
            // Stay Details
            $table->text('address_in_ghana_encrypted')->nullable();
            $table->string('host_name')->nullable();
            $table->string('host_phone')->nullable();
            $table->string('hotel_booking_path')->nullable();
            
            // Security Screening
            $table->boolean('denied_entry_before')->default(false);
            $table->boolean('criminal_conviction')->default(false);
            $table->boolean('previous_ghana_visa')->default(false);
            $table->text('travel_history')->nullable();
            
            // ETA Details
            $table->string('eta_number')->nullable()->unique();
            $table->string('qr_code')->nullable();
            $table->enum('status', ['pending', 'approved', 'denied', 'expired'])->default('pending');
            $table->integer('validity_days')->default(90);
            $table->enum('entry_type', ['single', 'multiple'])->default('single');
            
            // Processing
            $table->decimal('fee_amount', 10, 2)->default(0);
            $table->string('payment_status')->default('pending');
            $table->string('payment_reference')->nullable();
            
            // Timestamps
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['status', 'nationality_encrypted']);
            $table->index('intended_arrival_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eta_applications');
    }
};
