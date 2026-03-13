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
        Schema::create('service_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // standard, express, premium, emergency
            $table->string('name');
            $table->string('description')->nullable();
            $table->integer('processing_hours'); // SLA in hours
            $table->string('processing_time_display'); // "3-5 days", "24-48 hours"
            $table->decimal('fee_multiplier', 5, 2)->default(1.00); // 1.0 = base, 1.5 = +50%
            $table->decimal('additional_fee', 10, 2)->default(0); // flat additional fee
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_tiers');
    }
};
