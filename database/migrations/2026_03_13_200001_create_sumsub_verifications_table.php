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
        Schema::create('sumsub_verifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_id')->nullable();
            $table->unsignedBigInteger('eta_application_id')->nullable();
            $table->string('applicant_id')->unique();
            $table->string('external_user_id'); // Our internal user/application ID
            $table->enum('verification_status', ['pending', 'queued', 'completed', 'failed'])->default('pending');
            $table->enum('review_result', ['approved', 'rejected', 'pending', 'init'])->default('init');
            $table->string('review_reject_type')->nullable();
            $table->json('review_reject_details')->nullable();
            $table->json('verification_data')->nullable(); // Store full Sumsub response
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            
            $table->index(['application_id']);
            $table->index(['eta_application_id']);
            $table->index(['external_user_id']);
            $table->index(['verification_status']);
            $table->index(['review_result']);
            
            $table->foreign('application_id')
                ->references('id')
                ->on('applications')
                ->onDelete('cascade');
                
            $table->foreign('eta_application_id')
                ->references('id')
                ->on('eta_applications')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sumsub_verifications');
    }
};