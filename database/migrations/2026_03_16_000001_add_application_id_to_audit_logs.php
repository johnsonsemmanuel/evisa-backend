<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * RELATIONSHIP 4: Application → AuditLog (one-to-many)
     * Add dedicated application_id FK for efficient querying of application audit trails.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // Add application_id FK (nullable - some logs are not application-specific)
            $table->foreignId('application_id')
                ->nullable()
                ->after('user_id')
                ->constrained('applications')
                ->cascadeOnDelete();
            
            // Add index for efficient application audit log queries
            $table->index('application_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['application_id']);
            $table->dropIndex(['application_id']);
            $table->dropColumn('application_id');
        });
    }
};
