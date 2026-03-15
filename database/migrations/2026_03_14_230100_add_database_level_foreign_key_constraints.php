<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
            $orphanCount = DB::table('sessions as s')
                ->leftJoin('users as u', 's.user_id', '=', 'u.id')
                ->whereNotNull('s.user_id')
                ->whereNull('u.id')
                ->count();

            if ($orphanCount > 0) {
                throw new RuntimeException(
                    "Cannot add FK sessions.user_id -> users.id. Found {$orphanCount} orphaned session rows."
                );
            }

            // Check if constraint exists - with database driver compatibility
            $constraintExists = false;
            if (DB::getDriverName() === 'mysql') {
                $constraintExists = DB::table('INFORMATION_SCHEMA.KEY_COLUMN_USAGE')
                    ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
                    ->where('TABLE_NAME', 'sessions')
                    ->where('COLUMN_NAME', 'user_id')
                    ->whereNotNull('REFERENCED_TABLE_NAME')
                    ->exists();
            } else {
                // For SQLite, foreign keys are not enforced by default in tests
                // We'll just try to add the constraint and catch any exceptions
                $constraintExists = false;
            }

            if (! $constraintExists) {
                try {
                    Schema::table('sessions', function (Blueprint $table) {
                        $table->foreign('user_id')
                            ->references('id')
                            ->on('users')
                            ->onDelete('cascade')
                            ->onUpdate('cascade');
                    });
                } catch (\Exception $e) {
                    // Constraint might already exist, ignore the error
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
            // Check if constraint exists - with database driver compatibility
            $constraintExists = false;
            if (DB::getDriverName() === 'mysql') {
                $constraintExists = DB::table('INFORMATION_SCHEMA.KEY_COLUMN_USAGE')
                    ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
                    ->where('TABLE_NAME', 'sessions')
                    ->where('COLUMN_NAME', 'user_id')
                    ->whereNotNull('REFERENCED_TABLE_NAME')
                    ->exists();
            } else {
                // For SQLite, assume constraint exists and try to drop it
                $constraintExists = true;
            }

            if ($constraintExists) {
                try {
                    Schema::table('sessions', function (Blueprint $table) {
                        $table->dropForeign(['user_id']);
                    });
                } catch (\Exception $e) {
                    // Constraint might not exist, ignore the error
                }
            }
        }
    }
};
