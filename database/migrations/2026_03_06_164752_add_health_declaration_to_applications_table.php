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
        Schema::table('applications', function (Blueprint $table) {
            $table->string('health_good_condition')->nullable()->after('processing_fee');
            $table->string('health_recent_illness')->nullable()->after('health_good_condition');
            $table->string('health_contact_infectious')->nullable()->after('health_recent_illness');
            $table->string('health_yellow_fever_vaccinated')->nullable()->after('health_contact_infectious');
            $table->string('health_chronic_conditions')->nullable()->after('health_yellow_fever_vaccinated');
            $table->text('health_condition_details')->nullable()->after('health_chronic_conditions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn([
                'health_good_condition',
                'health_recent_illness',
                'health_contact_infectious',
                'health_yellow_fever_vaccinated',
                'health_chronic_conditions',
                'health_condition_details',
            ]);
        });
    }
};
