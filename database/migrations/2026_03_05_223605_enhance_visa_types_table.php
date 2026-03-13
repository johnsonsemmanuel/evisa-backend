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
        Schema::table('visa_types', function (Blueprint $table) {
            // Fee structure breakdown
            $table->decimal('government_fee', 10, 2)->default(0)->after('base_fee');
            $table->decimal('platform_fee', 10, 2)->default(0)->after('government_fee');
            
            // Visa characteristics
            $table->enum('entry_type', ['single', 'multiple'])->default('single')->after('platform_fee');
            $table->string('validity_period')->nullable()->after('entry_type'); // e.g., "30-90 days", "1 year"
            $table->string('category')->default('visa')->after('validity_period'); // visa, eta
            
            // Required fields configuration per visa type
            $table->json('required_fields')->nullable()->after('required_documents');
            $table->json('optional_fields')->nullable()->after('required_fields');
            
            // Processing defaults
            $table->integer('default_processing_days')->default(5)->after('optional_fields');
            $table->string('default_route_to')->default('gis')->after('default_processing_days');
            
            // Display order
            $table->integer('sort_order')->default(0)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visa_types', function (Blueprint $table) {
            $table->dropColumn([
                'government_fee',
                'platform_fee',
                'entry_type',
                'validity_period',
                'category',
                'required_fields',
                'optional_fields',
                'default_processing_days',
                'default_route_to',
                'sort_order',
            ]);
        });
    }
};
