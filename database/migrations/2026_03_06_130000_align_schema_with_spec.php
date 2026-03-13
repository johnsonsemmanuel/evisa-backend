<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add missing fields to applications table
        Schema::table('applications', function (Blueprint $table) {
            $table->string('visa_channel')->default('e-visa')->after('visa_type_id');
            $table->enum('entry_type', ['single', 'multiple'])->default('single')->after('visa_channel');
            $table->foreignId('mfa_mission_id')->nullable()->after('assigned_agency')
                  ->constrained('mfa_missions')->nullOnDelete();
            $table->string('current_queue')->nullable()->after('mfa_mission_id');
        });

        // 2. Add mission_id to users table
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('mfa_mission_id')->nullable()->after('agency')
                  ->constrained('mfa_missions')->nullOnDelete();
        });

        // 3. Add region field to mfa_missions table
        Schema::table('mfa_missions', function (Blueprint $table) {
            $table->string('region')->nullable()->after('country_name');
        });

        // 4. Create mission_country_mapping pivot table
        Schema::create('mission_country_mapping', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mfa_mission_id')->constrained('mfa_missions')->cascadeOnDelete();
            $table->string('country_code', 3)->index();
            $table->timestamps();

            $table->unique(['mfa_mission_id', 'country_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_country_mapping');

        Schema::table('mfa_missions', function (Blueprint $table) {
            $table->dropColumn('region');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['mfa_mission_id']);
            $table->dropColumn('mfa_mission_id');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['mfa_mission_id']);
            $table->dropColumn(['visa_channel', 'entry_type', 'mfa_mission_id', 'current_queue']);
        });
    }
};
