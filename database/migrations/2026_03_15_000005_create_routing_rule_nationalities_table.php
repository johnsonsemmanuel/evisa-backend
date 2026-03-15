<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * NORMALIZATION: Replace routing_rules.nationalities JSON array
     * with proper pivot table.
     */
    public function up(): void
    {
        Schema::create('routing_rule_nationalities', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignId('routing_rule_id')
                ->constrained('routing_rules')
                ->cascadeOnDelete();
            
            $table->char('country_code', 2); // ISO 3166-1 alpha-2 - index added below
            
            // Per-nationality routing override (optional)
            $table->enum('override_route_to', ['gis_hq', 'mfa_hq', 'mfa_mission'])->nullable();
            $table->foreignId('override_mfa_mission_id')
                ->nullable()
                ->constrained('mfa_missions')
                ->nullOnDelete();
            
            // Per-nationality SLA override
            $table->integer('override_sla_hours')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->unique(['routing_rule_id', 'country_code'], 'routing_nationality_unique');
            $table->index('country_code'); // Single index definition
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routing_rule_nationalities');
    }
};
