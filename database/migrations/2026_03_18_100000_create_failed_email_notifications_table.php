<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_email_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('mailable_class');
            $table->foreignId('application_id')->nullable()->constrained('applications')->nullOnDelete();
            $table->timestamp('attempted_at');
            $table->text('failure_reason')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_email_notifications');
    }
};
