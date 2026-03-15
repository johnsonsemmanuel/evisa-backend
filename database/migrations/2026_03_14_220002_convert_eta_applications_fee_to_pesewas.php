<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert eta_applications.fee_amount from DECIMAL to UNSIGNED BIGINT (pesewas).
     */
    public function up(): void
    {
        if (!Schema::hasColumn('eta_applications', 'fee_amount_decimal_backup')) {
            Schema::table('eta_applications', function (Blueprint $table) {
                $table->decimal('fee_amount_decimal_backup', 10, 2)->nullable()->after('fee_amount');
            });
        }
        DB::statement('UPDATE eta_applications SET fee_amount_decimal_backup = fee_amount');

        if (!Schema::hasColumn('eta_applications', 'fee_amount_pesewas')) {
            Schema::table('eta_applications', function (Blueprint $table) {
                $table->unsignedBigInteger('fee_amount_pesewas')->default(0)->after('fee_amount_decimal_backup');
            });
        }
        DB::statement('UPDATE eta_applications SET fee_amount_pesewas = COALESCE(ROUND(fee_amount * 100), 0)');

        $mismatch = DB::selectOne(
            'SELECT COUNT(*) as c FROM eta_applications WHERE fee_amount_pesewas != COALESCE(ROUND(fee_amount * 100), 0)'
        );
        if ($mismatch->c > 0) {
            throw new \RuntimeException("eta_applications.fee_amount: verification failed, {$mismatch->c} rows mismatch");
        }

        Schema::table('eta_applications', function (Blueprint $table) {
            $table->dropColumn('fee_amount');
        });
        Schema::table('eta_applications', function (Blueprint $table) {
            $table->renameColumn('fee_amount_pesewas', 'fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('eta_applications', function (Blueprint $table) {
            $table->decimal('fee_amount_decimal', 10, 2)->nullable()->after('fee_amount');
        });
        DB::statement('UPDATE eta_applications SET fee_amount_decimal = fee_amount / 100');
        Schema::table('eta_applications', function (Blueprint $table) {
            $table->dropColumn('fee_amount');
        });
        Schema::table('eta_applications', function (Blueprint $table) {
            $table->renameColumn('fee_amount_decimal', 'fee_amount');
        });
    }
};
