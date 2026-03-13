<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_authorizations', function (Blueprint $table) {
            $table->string('taid', 30)->primary(); // GH-TA-YYYYMMDD-XXXX
            $table->string('passport_number');
            $table->string('nationality', 2);
            $table->enum('authorization_type', ['ETA', 'VISA']);
            $table->enum('status', ['active', 'expired', 'used', 'cancelled'])->default('active');
            $table->timestamps();
            
            $table->index(['passport_number', 'nationality']);
            $table->index('authorization_type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_authorizations');
    }
};
