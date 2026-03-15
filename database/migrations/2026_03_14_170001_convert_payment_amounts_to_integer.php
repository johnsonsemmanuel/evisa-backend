<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * CRITICAL: Convert amount from DECIMAL to BIGINT (pesewas/cents)
     * This prevents floating point precision issues in financial calculations.
     */
    public function up(): void
    {
        // Step 1: Add new integer amount column
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('amount_pesewas')->nullable()->after('amount');
        });

        // Step 2: Convert existing decimal amounts to pesewas (multiply by 100)
        DB::statement('UPDATE payments SET amount_pesewas = CAST(amount * 100 AS UNSIGNED)');

        // Step 3: Make amount_pesewas NOT NULL now that data is migrated
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('amount_pesewas')->nullable(false)->change();
        });

        // Step 4: Drop old decimal amount column
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('amount');
        });

        // Step 5: Rename amount_pesewas to amount
        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('amount_pesewas', 'amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Add back decimal amount column
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount_decimal', 10, 2)->nullable()->after('amount');
        });

        // Step 2: Convert pesewas back to decimal (divide by 100)
        DB::statement('UPDATE payments SET amount_decimal = amount / 100');

        // Step 3: Drop integer amount column
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('amount');
        });

        // Step 4: Rename amount_decimal to amount
        Schema::table('payments', function (Blueprint $table) {
            $table->renameColumn('amount_decimal', 'amount');
        });
    }
};