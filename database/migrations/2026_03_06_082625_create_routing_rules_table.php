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
        Schema::create('routing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('rule_type', ['nationality', 'visa_type', 'risk_level', 'purpose', 'diplomatic', 'custom'])->index();
            $table->foreignId('visa_type_id')->nullable()->constrained()->nullOnDelete();
            $table->json('nationalities')->nullable();
            $table->json('conditions')->nullable();
            $table->enum('route_to', ['gis_hq', 'mfa_hq', 'mfa_mission'])->default('gis_hq');
            $table->foreignId('mfa_mission_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('priority')->default(100);
            $table->integer('sla_hours')->default(72);
            $table->boolean('requires_interview')->default(false);
            $table->boolean('requires_security_clearance')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['rule_type', 'is_active']);
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routing_rules');
    }
};
