<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Run this migration 30+ days after converting monetary columns to pesewas.
 * Drops the _decimal_backup columns used for rollback verification.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'applications' => ['total_fee', 'government_fee', 'platform_fee', 'processing_fee'],
            'eta_applications' => ['fee_amount'],
            'visa_types' => ['base_fee', 'government_fee', 'platform_fee', 'multiple_entry_fee'],
            'service_tiers' => ['additional_fee'],
        ];

        foreach ($tables as $table => $columns) {
            foreach ($columns as $col) {
                $backupCol = $col . '_decimal_backup';
                if (Schema::hasColumn($table, $backupCol)) {
                    Schema::table($table, function (Blueprint $t) use ($backupCol) {
                        $t->dropColumn($backupCol);
                    });
                }
            }
        }
    }

    public function down(): void
    {
        // No-op: backup columns are not recreated on rollback
    }
};
