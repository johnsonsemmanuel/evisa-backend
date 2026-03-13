<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visa_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');              // Tourism, Business
            $table->string('slug')->unique();    // tourism, business
            $table->text('description')->nullable();
            $table->decimal('base_fee', 10, 2);
            $table->integer('max_duration_days')->default(90);
            $table->boolean('is_active')->default(true);
            $table->json('required_documents');   // ['passport_bio', 'photo', 'invitation_letter']
            $table->json('eligible_nationalities')->nullable(); // null = all
            $table->json('blacklisted_nationalities')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_types');
    }
};
