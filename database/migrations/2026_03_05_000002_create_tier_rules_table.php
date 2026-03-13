<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tier_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visa_type_id')->constrained('visa_types')->cascadeOnDelete();
            $table->enum('tier', ['tier_1', 'tier_2']);
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('conditions');           // JSON rules: {"nationality_in": [...], "duration_gt": 30}
            $table->enum('route_to', ['gis', 'mfa'])->default('gis');
            $table->integer('sla_hours');         // 72 for tier_1, 120 for tier_2
            $table->integer('priority')->default(0); // Higher = evaluated first
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['visa_type_id', 'tier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tier_rules');
    }
};
