<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // 1. Add missing gateway field (enum) - with existence check
            if (!Schema::hasColumn('payments', 'gateway')) {
                $table->enum('gateway', ['gcb', 'paystack'])->after('user_id');
            }
            
            // 2. Rename provider_reference to gateway_reference for consistency
            if (Schema::hasColumn('payments', 'provider_reference') && !Schema::hasColumn('payments', 'gateway_reference')) {
                $table->renameColumn('provider_reference', 'gateway_reference');
            }
            
            // 3. Add raw_response field (stores full gateway response for reconciliation)
            if (!Schema::hasColumn('payments', 'raw_response')) {
                $table->json('raw_response')->nullable()->after('provider_response');
            }
            
            // 4. Add failure_reason field
            if (!Schema::hasColumn('payments', 'failure_reason')) {
                $table->text('failure_reason')->nullable()->after('paid_at');
            }
            
            // 5. Add soft deletes (payments are NEVER hard deleted)
            if (!Schema::hasColumn('payments', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // 6. Fix foreign key constraint - change from CASCADE to RESTRICT (with existence check)
        if (DB::getDriverName() === 'mysql') {
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'payments' 
                AND COLUMN_NAME = 'application_id' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            if (!empty($foreignKeys)) {
                Schema::table('payments', function (Blueprint $table) {
                    $table->dropForeign(['application_id']);
                    $table->foreign('application_id')->references('id')->on('applications')->onDelete('restrict');
                });
            }
        }

        // 7. Update existing 'completed' status to 'paid' before changing enum (with existence check)
        $completedCount = DB::table('payments')->where('status', 'completed')->count();
        if ($completedCount > 0) {
            DB::statement("UPDATE payments SET status = 'paid' WHERE status = 'completed'");
        }
        
        // 8. Update status enum to include all required statuses (with driver check)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('initiated','processing','paid','failed','refunded','cancelled','suspicious') NOT NULL DEFAULT 'initiated'");
        }

        // 9. Update currency default to GHS (Ghana Cedis) (with driver check)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY COLUMN currency CHAR(3) NOT NULL DEFAULT 'GHS'");
        }

        // 10. Add required indexes (with existence checks)
        Schema::table('payments', function (Blueprint $table) {
            if (DB::getDriverName() === 'mysql') {
                // Check if index doesn't already exist
                $indexes = DB::select("SHOW INDEX FROM payments WHERE Key_name = 'payments_gateway_reference_idx'");
                if (empty($indexes)) {
                    $table->index(['gateway', 'gateway_reference'], 'payments_gateway_reference_idx');
                }
                
                // Check if application_id index doesn't already exist
                $appIndexes = DB::select("SHOW INDEX FROM payments WHERE Key_name = 'payments_application_id_index'");
                if (empty($appIndexes)) {
                    $table->index('application_id');
                }
            } else {
                // For SQLite, just add indexes without checking (they'll be ignored if they exist)
                try {
                    $table->index(['gateway', 'gateway_reference'], 'payments_gateway_reference_idx');
                } catch (\Exception $e) {
                    // Index might already exist
                }
                
                try {
                    $table->index('application_id');
                } catch (\Exception $e) {
                    // Index might already exist
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // FIXED: Add existence checks for all operations
        
        Schema::table('payments', function (Blueprint $table) {
            // Remove indexes first (with existence checks)
            try {
                $table->dropIndex('payments_gateway_reference_idx');
            } catch (\Exception $e) {
                // Index might not exist
            }
            
            try {
                $table->dropIndex(['application_id']);
            } catch (\Exception $e) {
                // Index might not exist
            }
            
            // Remove added fields (with existence checks)
            if (Schema::hasColumn('payments', 'gateway')) {
                $table->dropColumn('gateway');
            }
            if (Schema::hasColumn('payments', 'raw_response')) {
                $table->dropColumn('raw_response');
            }
            if (Schema::hasColumn('payments', 'failure_reason')) {
                $table->dropColumn('failure_reason');
            }
            if (Schema::hasColumn('payments', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            
            // Rename back (with existence checks)
            if (Schema::hasColumn('payments', 'gateway_reference') && !Schema::hasColumn('payments', 'provider_reference')) {
                $table->renameColumn('gateway_reference', 'provider_reference');
            }
        });

        // Restore original foreign key constraint (with existence check)
        if (DB::getDriverName() === 'mysql') {
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'payments' 
                AND COLUMN_NAME = 'application_id' 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            if (!empty($foreignKeys)) {
                Schema::table('payments', function (Blueprint $table) {
                    $table->dropForeign(['application_id']);
                    $table->foreign('application_id')->references('id')->on('applications')->onDelete('cascade');
                });
            }
        }

        // Restore original status enum (with driver and data checks)
        if (DB::getDriverName() === 'mysql') {
            // First, migrate any 'paid' status back to 'completed'
            $paidCount = DB::table('payments')->where('status', 'paid')->count();
            if ($paidCount > 0) {
                DB::statement("UPDATE payments SET status = 'completed' WHERE status = 'paid'");
            }
            
            DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('pending','processing','completed','failed','refunded') NOT NULL DEFAULT 'pending'");
        }

        // Restore original currency default (with driver check)
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY COLUMN currency VARCHAR(3) NOT NULL DEFAULT 'USD'");
        }
    }
};