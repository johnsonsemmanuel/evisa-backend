<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * RELATIONSHIP 8: User → Agency (for GIS/MFA scoping)
     * 
     * Creates proper agency hierarchy and mission structure for officer scoping.
     */
    public function up(): void
    {
        // Agencies table (GIS HQ, MFA HQ, field offices)
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Ghana Immigration Service HQ, MFA HQ, etc.');
            $table->string('code', 20)->unique()->comment('GIS_HQ, MFA_HQ, GIS_ACCRA, etc.');
            $table->enum('type', ['gis_hq', 'gis_field', 'mfa_hq', 'mfa_mission', 'border_control', 'admin'])->index();
            $table->text('description')->nullable();
            
            // Hierarchical structure (e.g., field office → HQ)
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('agencies')
                ->nullOnDelete();
            
            // Contact information
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            
            // Geographic location
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->char('country_code', 2)->default('GH');
            
            // Operational status
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(100);
            
            $table->timestamps();
            
            // Indexes
            $table->index(['type', 'is_active']);
            $table->index('parent_id');
            $table->index(['country_code', 'type']);
        });
        
        // Missions table (MFA missions abroad - may already exist as mfa_missions)
        // Check if mfa_missions exists, if not create missions table
        if (!Schema::hasTable('mfa_missions')) {
            Schema::create('missions', function (Blueprint $table) {
                $table->id();
                $table->string('name')->comment('Ghana Embassy London, Ghana High Commission Ottawa');
                $table->string('code', 20)->unique()->comment('GH_EMB_UK, GH_HC_CA');
                $table->char('country_code', 2)->index()->comment('Country where mission is located');
                $table->string('city');
                
                // Link to parent agency (MFA HQ)
                $table->foreignId('agency_id')
                    ->constrained('agencies')
                    ->cascadeOnDelete();
                
                // Contact information
                $table->string('address')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('timezone')->default('UTC');
                
                // Operational details
                $table->boolean('can_issue_visa')->default(false);
                $table->boolean('requires_interview')->default(false);
                $table->integer('default_sla_hours')->default(120);
                $table->boolean('is_active')->default(true);
                
                $table->timestamps();
                
                // Indexes
                $table->index(['country_code', 'is_active']);
            });
        }
        
        // Add agency_id and mission_id to users table
        Schema::table('users', function (Blueprint $table) {
            // Agency assignment (for all officers)
            $table->foreignId('agency_id')
                ->nullable()
                ->after('agency')
                ->constrained('agencies')
                ->nullOnDelete();
            
            // Mission assignment (for MFA officers only)
            // Check if mfa_missions exists, use that, otherwise use missions
            $missionTable = Schema::hasTable('mfa_missions') ? 'mfa_missions' : 'missions';
            $table->foreignId('mission_id')
                ->nullable()
                ->after('agency_id')
                ->constrained($missionTable)
                ->nullOnDelete();
            
            // Indexes
            $table->index('agency_id');
            $table->index('mission_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign keys and columns from users
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['mission_id']);
            $table->dropForeign(['agency_id']);
            $table->dropIndex(['mission_id']);
            $table->dropIndex(['agency_id']);
            $table->dropColumn(['agency_id', 'mission_id']);
        });
        
        // Drop missions table if we created it
        if (Schema::hasTable('missions') && !Schema::hasTable('mfa_missions')) {
            Schema::dropIfExists('missions');
        }
        
        // Drop agencies table
        Schema::dropIfExists('agencies');
    }
};
