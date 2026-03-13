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
        Schema::create('risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->integer('risk_score')->default(0);
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->json('factors')->nullable();
            $table->boolean('watchlist_match')->default(false);
            $table->json('watchlist_matches')->nullable();
            $table->boolean('document_verified')->default(false);
            $table->json('document_checks')->nullable();
            $table->boolean('nationality_risk')->default(false);
            $table->boolean('travel_history_risk')->default(false);
            $table->boolean('previous_denial')->default(false);
            $table->boolean('overstay_history')->default(false);
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'in_progress', 'completed', 'manual_review'])->default('pending');
            $table->foreignId('assessed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assessed_at')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'status']);
            $table->index('risk_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('risk_assessments');
    }
};
