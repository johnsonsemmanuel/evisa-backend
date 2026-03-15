<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert visa_types fee columns from DECIMAL to UNSIGNED BIGINT (pesewas).
     */
    public function up(): void
    {
        $columns = ['base_fee', 'government_fee', 'platform_fee', 'multiple_entry_fee'];

        foreach ($columns as $col) {
            if (!Schema::hasColumn('visa_types', $col)) {
                continue;
            }
            if (!Schema::hasColumn('visa_types', $col . '_decimal_backup')) {
                Schema::table('visa_types', function (Blueprint $table) use ($col) {
                    $table->decimal($col . '_decimal_backup', 10, 2)->nullable()->after($col);
                });
            }
            DB::statement("UPDATE visa_types SET {$col}_decimal_backup = {$col}");

            if (!Schema::hasColumn('visa_types', $col . '_pesewas')) {
                Schema::table('visa_types', function (Blueprint $table) use ($col) {
                    $table->unsignedBigInteger($col . '_pesewas')->default(0)->after($col . '_decimal_backup');
                });
            }
            DB::statement("UPDATE visa_types SET {$col}_pesewas = COALESCE(ROUND({$col} * 100), 0)");

            $mismatch = DB::selectOne(
                "SELECT COUNT(*) as c FROM visa_types WHERE {$col}_pesewas != COALESCE(ROUND({$col} * 100), 0)"
            );
            if ($mismatch->c > 0) {
                throw new \RuntimeException("visa_types.{$col}: verification failed, {$mismatch->c} rows mismatch");
            }

            Schema::table('visa_types', function (Blueprint $table) use ($col) {
                $table->dropColumn($col);
            });
            Schema::table('visa_types', function (Blueprint $table) use ($col) {
                $table->renameColumn($col . '_pesewas', $col);
            });
        }
    }

    public function down(): void
    {
        $columns = ['base_fee', 'government_fee', 'platform_fee', 'multiple_entry_fee'];

        foreach ($columns as $col) {
            if (!Schema::hasColumn('visa_types', $col)) {
                continue;
            }
            Schema::table('visa_types', function (Blueprint $table) use ($col) {
                $table->decimal($col . '_decimal', 10, 2)->nullable()->after($col);
            });
            DB::statement("UPDATE visa_types SET {$col}_decimal = {$col} / 100");
            Schema::table('visa_types', function (Blueprint $table) use ($col) {
                $table->dropColumn($col);
            });
            Schema::table('visa_types', function (Blueprint $table) use ($col) {
                $table->renameColumn($col . '_decimal', $col);
            });
        }
    }
};
