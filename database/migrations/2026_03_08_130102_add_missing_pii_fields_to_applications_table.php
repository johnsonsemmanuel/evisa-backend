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
            $table->string('gender')->nullable()->after('date_of_birth_encrypted');
            $table->string('marital_status')->nullable()->after('gender');
            $table->string('profession_encrypted')->nullable()->after('marital_status');
            $table->string('country_of_birth')->nullable()->after('profession_encrypted');
            $table->date('passport_issue_date')->nullable()->after('passport_number_encrypted');
            $table->date('passport_expiry')->nullable()->after('passport_issue_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn([
                'gender',
                'marital_status',
                'profession_encrypted',
                'country_of_birth',
                'passport_issue_date',
                'passport_expiry'
            ]);
        });
    }
};
