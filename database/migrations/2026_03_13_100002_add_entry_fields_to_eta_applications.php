<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('eta_applications', function (Blueprint $table) {
            // Check if columns don't exist before adding (Week 1 may have added them)
            if (!Schema::hasColumn('eta_applications', 'entry_date')) {
                $table->timestamp('entry_date')->nullable()->after('expires_at');
            }
            if (!Schema::hasColumn('eta_applications', 'port_of_entry_actual')) {
                $table->string('port_of_entry_actual', 100)->nullable()->after('entry_date');
            }
            if (!Schema::hasColumn('eta_applications', 'entry_officer_id')) {
                $table->unsignedBigInteger('entry_officer_id')->nullable()->after('port_of_entry_actual');
            }
            
            // Add index and foreign key only if column was added or doesn't have them
            if (!Schema::hasColumn('eta_applications', 'entry_officer_id') || 
                !collect(Schema::getIndexes('eta_applications'))->contains('name', 'idx_entry_officer')) {
                $table->index('entry_officer_id', 'idx_entry_officer');
            }
            
            // Add foreign key if it doesn't exist
            $foreignKeys = collect(Schema::getForeignKeys('eta_applications'))
                ->pluck('columns')
                ->flatten()
                ->toArray();
                
            if (!in_array('entry_officer_id', $foreignKeys)) {
                $table->foreign('entry_officer_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eta_applications', function (Blueprint $table) {
            // Only drop if they exist and were added by this migration
            if (Schema::hasColumn('eta_applications', 'entry_officer_id')) {
                $foreignKeys = collect(Schema::getForeignKeys('eta_applications'))
                    ->pluck('columns')
                    ->flatten()
                    ->toArray();
                    
                if (in_array('entry_officer_id', $foreignKeys)) {
                    $table->dropForeign(['entry_officer_id']);
                }
                
                if (collect(Schema::getIndexes('eta_applications'))->contains('name', 'idx_entry_officer')) {
                    $table->dropIndex('idx_entry_officer');
                }
            }
            
            // Note: We don't drop the columns as they may have been added by Week 1
            // This migration is primarily for adding indexes and foreign keys
        });
    }
};
