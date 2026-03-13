<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->string('document_type');       // passport_bio, photo, invitation_letter, etc.
            $table->string('original_filename');
            $table->string('stored_path');          // Encrypted storage path
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size'); // bytes
            $table->enum('ocr_status', ['pending', 'processing', 'passed', 'failed', 'skipped'])->default('pending');
            $table->text('ocr_result')->nullable();
            $table->enum('verification_status', ['pending', 'accepted', 'rejected', 'reupload_requested'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_documents');
    }
};
