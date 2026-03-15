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
        Schema::table('applications', function (Blueprint $table) {
            // Check if columns don't exist before adding
            if (!Schema::hasColumn('applications', 'last_entry_date')) {
                $table->timestamp('last_entry_date')->nullable();
            }
            if (!Schema::hasColumn('applications', 'last_port_of_entry')) {
                $table->string('last_port_of_entry', 100)->nullable();
            }
            if (!Schema::hasColumn('applications', 'last_entry_officer_id')) {
                $table->unsignedBigInteger('last_entry_officer_id')->nullable();
                
                $table->index('last_entry_officer_id', 'idx_last_entry_officer');
                
                $table->foreign('last_entry_officer_id')
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
        Schema::table('applications', function (Blueprint $table) {
            if (Schema::hasColumn('applications', 'last_entry_officer_id')) {
                $table->dropForeign(['last_entry_officer_id']);
                $table->dropIndex('idx_last_entry_officer');
            }
            
            $columns = ['last_entry_date', 'last_port_of_entry', 'last_entry_officer_id'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
