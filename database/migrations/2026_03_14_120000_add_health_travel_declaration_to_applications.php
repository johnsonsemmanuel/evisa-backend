<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // Add health declaration for travel to affected countries
            $table->boolean('health_declaration_travel_affected')->default(false)->after('health_condition_details');
            $table->text('health_declaration_affected_countries')->nullable()->after('health_declaration_travel_affected');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['health_declaration_travel_affected', 'health_declaration_affected_countries']);
        });
    }
};
