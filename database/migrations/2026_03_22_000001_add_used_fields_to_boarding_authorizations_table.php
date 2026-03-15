<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boarding_authorizations', function (Blueprint $table) {
            $table->timestamp('used_at')->nullable()->after('verified_by_user_id');
            $table->unsignedBigInteger('used_by_user_id')->nullable()->after('used_at');

            $table->foreign('used_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('boarding_authorizations', function (Blueprint $table) {
            $table->dropForeign(['used_by_user_id']);
            $table->dropColumn(['used_at', 'used_by_user_id']);
        });
    }
};
