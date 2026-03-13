<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // For SQLite, status is stored as string so no enum modification needed
        // The Application model will handle the valid statuses
        
        // Add reviewed_by field to track who submitted for approval
        Schema::table('applications', function (Blueprint $table) {
            $table->unsignedBigInteger('reviewed_by_id')->nullable()->after('assigned_officer_id');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_id');
            
            $table->foreign('reviewed_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by_id']);
            $table->dropColumn(['reviewed_by_id', 'reviewed_at']);
        });
    }
};
