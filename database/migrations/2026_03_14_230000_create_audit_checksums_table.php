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
        Schema::create('audit_checksums', function (Blueprint $table) {
            $table->id();
            $table->date('audit_date')->unique()->comment('Date of audited logs');
            $table->integer('record_count')->comment('Number of audit log records for this date');
            $table->string('checksum', 64)->comment('SHA-256 checksum of all audit log IDs');
            $table->string('data_hash', 64)->comment('SHA-256 hash of concatenated critical fields');
            $table->timestamp('verified_at')->useCurrent()->comment('When this checksum was computed');
            $table->string('verified_by')->nullable()->comment('User or system that verified');
            
            $table->index('audit_date');
            $table->index('verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_checksums');
    }
};
