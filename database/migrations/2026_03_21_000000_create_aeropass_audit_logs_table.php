<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aeropass_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('interaction_type', 64); // nominal_check_submitted, evisa_record_check, callback_received
            $table->text('request_payload');  // encrypted
            $table->text('response_payload'); // encrypted
            $table->timestamp('performed_at');
            $table->timestamps();

            $table->index(['application_id', 'interaction_type']);
            $table->index('performed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aeropass_audit_logs');
    }
};
