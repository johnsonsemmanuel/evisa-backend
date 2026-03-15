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
        Schema::table('payments', function (Blueprint $table) {
            // Critical payment data - must never be null
            $table->unsignedBigInteger('application_id')->nullable(false)->change();
            $table->string('gateway')->nullable(false)->change();
            $table->unsignedBigInteger('amount')->nullable(false)->change();
            $table->string('currency')->nullable(false)->default('GHS')->change();
            $table->string('status')->nullable(false)->change();
            
            // Laravel timestamps - should never be null
            $table->timestamp('created_at')->nullable(false)->change();
            $table->timestamp('updated_at')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('application_id')->nullable()->change();
            $table->string('gateway')->nullable()->change();
            $table->unsignedBigInteger('amount')->nullable()->change();
            $table->string('currency')->nullable()->change();
            $table->string('status')->nullable()->change();
            $table->timestamp('created_at')->nullable()->change();
            $table->timestamp('updated_at')->nullable()->change();
        });
    }
};