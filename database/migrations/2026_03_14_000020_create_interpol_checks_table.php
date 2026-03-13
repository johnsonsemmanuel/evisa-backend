<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interpol_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->onDelete('cascade');
            $table->string('unique_reference_id')->unique();
            $table->string('first_name');
            $table->string('surname');
            $table->date('date_of_birth');
            $table->enum('status', ['pending', 'matched', 'no_match', 'failed'])->default('pending');
            $table->boolean('interpol_nominal_matched')->nullable();
            $table->json('aeropass_response')->nullable();
            $table->timestamp('callback_received_at')->nullable();
            $table->integer('retry_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('unique_reference_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interpol_checks');
    }
};