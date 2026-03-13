<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eta_applications', function (Blueprint $table) {
            $table->string('taid', 30)->nullable()->after('id');
            $table->index('taid');
        });
        
        // Add foreign key after index
        Schema::table('eta_applications', function (Blueprint $table) {
            $table->foreign('taid')
                  ->references('taid')
                  ->on('travel_authorizations')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('eta_applications', function (Blueprint $table) {
            $table->dropForeign(['taid']);
            $table->dropIndex(['taid']);
            $table->dropColumn('taid');
        });
    }
};
