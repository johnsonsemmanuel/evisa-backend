<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert service_tiers.additional_fee from DECIMAL to UNSIGNED BIGINT (pesewas).
     * fee_multiplier remains decimal (dimensionless multiplier).
     */
    public function up(): void
    {
        $col = 'additional_fee';

        if (!Schema::hasColumn('service_tiers', $col . '_decimal_backup')) {
            Schema::table('service_tiers', function (Blueprint $table) use ($col) {
                $table->decimal($col . '_decimal_backup', 10, 2)->nullable()->after($col);
            });
        }
        DB::statement("UPDATE service_tiers SET {$col}_decimal_backup = {$col}");

        if (!Schema::hasColumn('service_tiers', $col . '_pesewas')) {
            Schema::table('service_tiers', function (Blueprint $table) use ($col) {
                $table->unsignedBigInteger($col . '_pesewas')->default(0)->after($col . '_decimal_backup');
            });
        }
        DB::statement("UPDATE service_tiers SET {$col}_pesewas = COALESCE(ROUND({$col} * 100), 0)");

        $mismatch = DB::selectOne(
            "SELECT COUNT(*) as c FROM service_tiers WHERE {$col}_pesewas != COALESCE(ROUND({$col} * 100), 0)"
        );
        if ($mismatch->c > 0) {
            throw new \RuntimeException("service_tiers.{$col}: verification failed, {$mismatch->c} rows mismatch");
        }

        Schema::table('service_tiers', function (Blueprint $table) use ($col) {
            $table->dropColumn($col);
        });
        Schema::table('service_tiers', function (Blueprint $table) use ($col) {
            $table->renameColumn($col . '_pesewas', $col);
        });
    }

    public function down(): void
    {
        $col = 'additional_fee';
        Schema::table('service_tiers', function (Blueprint $table) use ($col) {
            $table->decimal($col . '_decimal', 10, 2)->nullable()->after($col);
        });
        DB::statement("UPDATE service_tiers SET {$col}_decimal = {$col} / 100");
        Schema::table('service_tiers', function (Blueprint $table) use ($col) {
            $table->dropColumn($col);
        });
        Schema::table('service_tiers', function (Blueprint $table) use ($col) {
            $table->renameColumn($col . '_decimal', $col);
        });
    }
};
