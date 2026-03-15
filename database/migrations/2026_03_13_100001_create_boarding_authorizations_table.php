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
        Schema::create('boarding_authorizations', function (Blueprint $table) {
            $table->string('authorization_code', 30)->primary();
            $table->string('passport_number', 255); // Changed from text to string for indexing
            $table->char('nationality', 2);
            $table->enum('authorization_type', ['ETA', 'VISA']);
            $table->string('eta_number', 30)->nullable();
            $table->string('visa_id', 50)->nullable();
            $table->timestamp('verification_timestamp');
            $table->timestamp('expiry_timestamp');
            $table->unsignedBigInteger('verified_by_user_id')->nullable();
            $table->timestamps();
            
            $table->index(['passport_number', 'nationality'], 'idx_passport_nationality');
            $table->index('expiry_timestamp', 'idx_expiry');
            $table->index('eta_number', 'idx_eta_number');
            $table->index('visa_id', 'idx_visa_id');
            
            $table->foreign('verified_by_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boarding_authorizations');
    }
};
