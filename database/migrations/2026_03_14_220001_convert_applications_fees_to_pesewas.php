<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert application fee columns from DECIMAL to UNSIGNED BIGINT (pesewas).
     * CRITICAL: Run in transaction; backup columns kept for 30 days then drop in separate migration.
     */
    public function up(): void
    {
        $columns = [
            'total_fee',
            'government_fee',
            'platform_fee',
            'processing_fee',
        ];

        foreach ($columns as $col) {
            // Backup original decimal (keep 30 days)
            if (!Schema::hasColumn('applications', $col . '_decimal_backup')) {
                Schema::table('applications', function (Blueprint $table) use ($col) {
                    $table->decimal($col . '_decimal_backup', 10, 2)->nullable()->after($col);
                });
            }
            DB::statement("UPDATE applications SET {$col}_decimal_backup = {$col}");

            // New integer column
            if (!Schema::hasColumn('applications', $col . '_pesewas')) {
                Schema::table('applications', function (Blueprint $table) use ($col) {
                    $table->unsignedBigInteger($col . '_pesewas')->default(0)->after($col . '_decimal_backup');
                });
            }
            DB::statement("UPDATE applications SET {$col}_pesewas = COALESCE(ROUND({$col} * 100), 0)");

            // Verification: must be 0 mismatches
            $mismatch = DB::selectOne(
                "SELECT COUNT(*) as c FROM applications WHERE {$col}_pesewas != COALESCE(ROUND({$col} * 100), 0)"
            );
            if ($mismatch->c > 0) {
                throw new \RuntimeException("applications.{$col}: verification failed, {$mismatch->c} rows mismatch");
            }

            Schema::table('applications', function (Blueprint $table) use ($col) {
                $table->dropColumn($col);
            });
            Schema::table('applications', function (Blueprint $table) use ($col) {
                $table->renameColumn($col . '_pesewas', $col);
            });
        }
    }

    public function down(): void
    {
        $columns = ['total_fee', 'government_fee', 'platform_fee', 'processing_fee'];

        foreach ($columns as $col) {
            Schema::table('applications', function (Blueprint $table) use ($col) {
                $table->decimal($col . '_decimal', 10, 2)->nullable()->after($col);
            });
            DB::statement("UPDATE applications SET {$col}_decimal = {$col} / 100");
            Schema::table('applications', function (Blueprint $table) use ($col) {
                $table->dropColumn($col);
            });
            Schema::table('applications', function (Blueprint $table) use ($col) {
                $table->renameColumn($col . '_decimal', $col);
            });
        }
    }
};
