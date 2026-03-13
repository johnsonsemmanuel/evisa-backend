<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique(); // APP-2026-XXXXX
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('visa_type_id')->constrained('visa_types');

            // Encrypted PII fields (stored as AES encrypted text)
            $table->text('first_name_encrypted');
            $table->text('last_name_encrypted');
            $table->text('date_of_birth_encrypted');
            $table->text('passport_number_encrypted');
            $table->text('nationality_encrypted');
            $table->text('email_encrypted');
            $table->text('phone_encrypted')->nullable();

            // Non-PII travel details (nullable for draft stage)
            $table->date('intended_arrival')->nullable();
            $table->integer('duration_days')->nullable();
            $table->text('address_in_ghana')->nullable();
            $table->text('purpose_of_visit')->nullable();

            // Processing fields
            $table->enum('status', [
                'draft',
                'pending_payment',
                'submitted',
                'under_review',
                'additional_info_requested',
                'escalated',
                'approved',
                'denied',
                'cancelled',
            ])->default('draft');

            $table->enum('tier', ['tier_1', 'tier_2'])->nullable();
            $table->enum('assigned_agency', ['gis', 'mfa'])->nullable();
            $table->foreignId('assigned_officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('current_step')->default(1); // Wizard step tracking

            // SLA tracking
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('sla_deadline')->nullable();
            $table->timestamp('decided_at')->nullable();

            // Decision
            $table->text('decision_notes')->nullable();
            $table->string('evisa_file_path')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('tier');
            $table->index('assigned_agency');
            $table->index('assigned_officer_id');
            $table->index('sla_deadline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
