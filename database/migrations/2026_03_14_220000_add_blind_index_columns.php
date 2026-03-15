<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds blind index columns for searchable encrypted PII fields.
     * Blind indexes are one-way HMAC-SHA256 hashes used for equality searches.
     * 
     * SECURITY:
     * - Index columns are NOT encrypted (they're hashes for searching)
     * - Cannot be reversed to get plaintext
     * - Uses separate BLIND_INDEX_KEY (not APP_KEY)
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // Blind index for passport_number (most commonly searched)
            $table->string('passport_number_idx', 64)->nullable()->after('passport_number_encrypted');
            $table->index('passport_number_idx', 'idx_passport_number_blind');

            // Blind index for email (for duplicate detection and search)
            $table->string('email_idx', 64)->nullable()->after('email_encrypted');
            $table->index('email_idx', 'idx_email_blind');

            // Blind index for phone (for duplicate detection and search)
            $table->string('phone_idx', 64)->nullable()->after('phone_encrypted');
            $table->index('phone_idx', 'idx_phone_blind');
        });

        // Add blind indexes to eta_applications table if it exists
        if (Schema::hasTable('eta_applications')) {
            Schema::table('eta_applications', function (Blueprint $table) {
                if (Schema::hasColumn('eta_applications', 'passport_number_encrypted')) {
                    $table->string('passport_number_idx', 64)->nullable()->after('passport_number_encrypted');
                    $table->index('passport_number_idx', 'idx_eta_passport_number_blind');
                }

                if (Schema::hasColumn('eta_applications', 'email_encrypted')) {
                    $table->string('email_idx', 64)->nullable()->after('email_encrypted');
                    $table->index('email_idx', 'idx_eta_email_blind');
                }

                if (Schema::hasColumn('eta_applications', 'phone_encrypted')) {
                    $table->string('phone_idx', 64)->nullable()->after('phone_encrypted');
                    $table->index('phone_idx', 'idx_eta_phone_blind');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex('idx_passport_number_blind');
            $table->dropColumn('passport_number_idx');

            $table->dropIndex('idx_email_blind');
            $table->dropColumn('email_idx');

            $table->dropIndex('idx_phone_blind');
            $table->dropColumn('phone_idx');
        });

        if (Schema::hasTable('eta_applications')) {
            Schema::table('eta_applications', function (Blueprint $table) {
                if (Schema::hasColumn('eta_applications', 'passport_number_idx')) {
                    $table->dropIndex('idx_eta_passport_number_blind');
                    $table->dropColumn('passport_number_idx');
                }

                if (Schema::hasColumn('eta_applications', 'email_idx')) {
                    $table->dropIndex('idx_eta_email_blind');
                    $table->dropColumn('email_idx');
                }

                if (Schema::hasColumn('eta_applications', 'phone_idx')) {
                    $table->dropIndex('idx_eta_phone_blind');
                    $table->dropColumn('phone_idx');
                }
            });
        }
    }
};
