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
        Schema::create('watchlists', function (Blueprint $table) {
            $table->id();
            $table->enum('list_type', ['interpol', 'national', 'travel_ban', 'fraud', 'overstay', 'custom'])->index();
            $table->string('first_name_encrypted');
            $table->string('last_name_encrypted');
            $table->date('date_of_birth')->nullable();
            $table->string('nationality', 2)->nullable()->index();
            $table->string('passport_number_encrypted')->nullable();
            $table->string('id_number_encrypted')->nullable();
            $table->text('reason')->nullable();
            $table->string('source')->nullable();
            $table->string('source_reference')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('added_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('watchlists');
    }
};
