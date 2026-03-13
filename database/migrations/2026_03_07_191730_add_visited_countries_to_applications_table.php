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
            $table->string('visited_country_1', 2)->nullable()->after('purpose_of_visit');
            $table->string('visited_country_2', 2)->nullable()->after('visited_country_1');
            $table->string('visited_country_3', 2)->nullable()->after('visited_country_2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['visited_country_1', 'visited_country_2', 'visited_country_3']);
        });
    }
};
