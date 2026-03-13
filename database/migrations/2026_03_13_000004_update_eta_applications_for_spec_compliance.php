<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eta_applications', function (Blueprint $table) {
            // Update eta_number length for new format GH-ETA-YYYYMMDD-XXXX
            $table->string('eta_number', 30)->nullable()->change();
            
            // Add screening notes for flagged applications
            $table->json('screening_notes')->nullable()->after('status');
            
            // Add entry tracking fields
            $table->timestamp('entry_date')->nullable()->after('expires_at');
            $table->string('port_of_entry_actual')->nullable()->after('entry_date');
            $table->unsignedBigInteger('entry_officer_id')->nullable()->after('port_of_entry_actual');
            
            // Rename valid_from/valid_until to match spec
            if (!Schema::hasColumn('eta_applications', 'valid_from')) {
                $table->timestamp('valid_from')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('eta_applications', 'valid_until')) {
                $table->timestamp('valid_until')->nullable()->after('valid_from');
            }
        });
    }

    public function down(): void
    {
        Schema::table('eta_applications', function (Blueprint $table) {
            $table->dropColumn([
                'screening_notes',
                'entry_date',
                'port_of_entry_actual',
                'entry_officer_id',
            ]);
            
            if (Schema::hasColumn('eta_applications', 'valid_from')) {
                $table->dropColumn('valid_from');
            }
            if (Schema::hasColumn('eta_applications', 'valid_until')) {
                $table->dropColumn('valid_until');
            }
        });
    }
};
