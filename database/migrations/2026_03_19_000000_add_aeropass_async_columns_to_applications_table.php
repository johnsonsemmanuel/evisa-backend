<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('aeropass_transaction_ref', 100)->nullable();
            $table->string('aeropass_status', 32)->default('pending');
            $table->timestamp('aeropass_submitted_at')->nullable();
            $table->timestamp('aeropass_result_at')->nullable();
            $table->text('aeropass_raw_result')->nullable(); // encrypted at app layer
            $table->unsignedTinyInteger('aeropass_retry_count')->default(0);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->index('aeropass_transaction_ref');
            $table->index(['aeropass_status', 'aeropass_submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex(['aeropass_status', 'aeropass_submitted_at']);
            $table->dropIndex(['aeropass_transaction_ref']);
        });
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn([
                'aeropass_transaction_ref',
                'aeropass_status',
                'aeropass_submitted_at',
                'aeropass_result_at',
                'aeropass_raw_result',
                'aeropass_retry_count',
            ]);
        });
    }
};
