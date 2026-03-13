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
        Schema::create('mfa_missions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('city');
            $table->string('country_code', 2)->index();
            $table->string('country_name');
            $table->enum('mission_type', ['embassy', 'consulate', 'high_commission', 'permanent_mission'])->default('embassy');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('timezone')->default('UTC');
            $table->json('covered_nationalities')->nullable();
            $table->json('visa_types_handled')->nullable();
            $table->boolean('can_issue_visa')->default(false);
            $table->boolean('requires_interview')->default(false);
            $table->integer('default_sla_hours')->default(120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['country_code', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mfa_missions');
    }
};
