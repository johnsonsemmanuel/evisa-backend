<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * NORMALIZATION: Replace visa_types.required_documents JSON array
     * with proper tables: document_types (lookup) and visa_type_required_documents (pivot).
     */
    public function up(): void
    {
        // Create document_types lookup table
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            
            // Document identification
            $table->string('slug', 100)->unique(); // e.g., 'passport_bio', 'photo', 'invitation_letter'
            $table->string('name'); // Human-readable name
            $table->text('description')->nullable();
            
            // File requirements
            $table->string('allowed_formats')->default('pdf,jpg,jpeg,png'); // Comma-separated
            $table->integer('max_file_size_kb')->default(5120); // 5MB default
            $table->integer('min_file_size_kb')->default(10); // 10KB minimum
            
            // Validation rules
            $table->boolean('requires_ocr_verification')->default(false);
            $table->boolean('requires_face_match')->default(false);
            $table->text('validation_rules')->nullable(); // JSON or text
            
            // Display settings
            $table->integer('sort_order')->default(100);
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            // Indexes
            $table->index(['is_active', 'sort_order']);
        });
        
        // Create pivot table
        Schema::create('visa_type_required_documents', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys
            $table->foreignId('visa_type_id')
                ->constrained('visa_types')
                ->cascadeOnDelete();
            
            $table->foreignId('document_type_id')
                ->constrained('document_types')
                ->cascadeOnDelete();
            
            // Document requirement details
            $table->boolean('is_mandatory')->default(true); // vs optional
            $table->boolean('is_conditional')->default(false); // Required only if certain conditions met
            $table->text('condition_description')->nullable(); // e.g., "Required for stays > 90 days"
            
            // Override file requirements (if different from document_type defaults)
            $table->integer('override_max_file_size_kb')->nullable();
            $table->string('override_allowed_formats')->nullable();
            
            // Display settings
            $table->integer('sort_order')->default(100);
            $table->text('help_text')->nullable(); // Visa-type-specific instructions
            
            $table->timestamps();
            
            // Indexes
            $table->unique(['visa_type_id', 'document_type_id'], 'visa_document_unique');
            $table->index(['document_type_id', 'is_mandatory']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visa_type_required_documents');
        Schema::dropIfExists('document_types');
    }
};
