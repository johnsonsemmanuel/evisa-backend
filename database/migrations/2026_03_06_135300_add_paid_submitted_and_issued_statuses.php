<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE applications MODIFY COLUMN status ENUM(
                'draft',
                'submitted_awaiting_payment',
                'pending_payment',
                'paid_submitted',
                'submitted',
                'under_review',
                'additional_info_requested',
                'escalated',
                'pending_approval',
                'approved',
                'denied',
                'issued',
                'cancelled'
            ) DEFAULT 'draft'");
        }
        // SQLite: no enum enforcement, values work as strings
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE applications MODIFY COLUMN status ENUM(
                'draft',
                'submitted_awaiting_payment',
                'pending_payment',
                'submitted',
                'under_review',
                'additional_info_requested',
                'escalated',
                'pending_approval',
                'approved',
                'denied',
                'cancelled'
            ) DEFAULT 'draft'");
        }
    }
};
