<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Skip if mfa_missions already exists
        if (Schema::hasTable('mfa_missions')) {
            // Just create mission_country_mappings if it doesn't exist
            if (!Schema::hasTable('mission_country_mappings')) {
                Schema::create('mission_country_mappings', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('mfa_mission_id')->constrained('mfa_missions')->cascadeOnDelete();
                    $table->string('country_code', 3);
                    $table->string('country_name');
                    $table->boolean('is_primary')->default(true);
                    $table->timestamps();

                    $table->unique(['country_code', 'mfa_mission_id']);
                    $table->index('country_code');
                });
            }
            return;
        }

        Schema::create('mfa_missions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('city');
            $table->string('country');
            $table->string('country_code', 3);
            $table->enum('type', ['embassy', 'consulate', 'high_commission'])->default('embassy');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Mission Country Mapping - maps applicant countries to MFA missions
        Schema::create('mission_country_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mfa_mission_id')->constrained('mfa_missions')->cascadeOnDelete();
            $table->string('country_code', 3);
            $table->string('country_name');
            $table->boolean('is_primary')->default(true);
            $table->timestamps();

            $table->unique(['country_code', 'mfa_mission_id']);
            $table->index('country_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_country_mappings');
        Schema::dropIfExists('mfa_missions');
    }
};
