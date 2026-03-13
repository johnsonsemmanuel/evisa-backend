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
        Schema::create('border_crossings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('eta_application_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('crossing_type', ['entry', 'exit'])->index();
            $table->string('port_of_entry')->index();
            $table->string('passport_number_encrypted');
            $table->string('nationality', 2)->nullable();
            $table->string('traveler_name_encrypted');
            $table->enum('verification_status', ['valid', 'invalid', 'expired', 'not_found', 'secondary_inspection'])->default('valid');
            $table->text('verification_notes')->nullable();
            $table->string('flight_number')->nullable();
            $table->string('airline')->nullable();
            $table->foreignId('officer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('officer_badge')->nullable();
            $table->timestamp('crossed_at')->useCurrent();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'crossing_type']);
            $table->index(['crossed_at', 'port_of_entry']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('border_crossings');
    }
};
