<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * RELATIONSHIP 7: User → Role → Permissions (RBAC tables)
     * 
     * Full Role-Based Access Control system for government eVisa platform.
     * Supports 8 roles with granular permissions.
     * 
     * NOTE: Some tables may already exist from Spatie permissions package
     */
    public function up(): void
    {
        // Roles table - check if it doesn't already exist (Spatie permissions creates it)
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name', 50)->unique()->comment('Machine name: applicant, gis_officer, etc.');
                $table->string('display_name', 100)->comment('Human-readable name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(100);
                $table->timestamps();
                
                $table->index(['is_active', 'sort_order']);
            });
        }
        
        // Permissions table - check if it doesn't already exist (Spatie permissions creates it)
        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique()->comment('e.g., application.approve, payment.refund');
                $table->string('group', 50)->index()->comment('Grouping: application, payment, user, system');
                $table->string('display_name', 100);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                
                $table->index(['group', 'is_active']);
            });
        }
        
        // Role-Permission pivot table - check if it doesn't already exist (Spatie creates role_has_permissions)
        if (!Schema::hasTable('role_permissions') && !Schema::hasTable('role_has_permissions')) {
            Schema::create('role_permissions', function (Blueprint $table) {
                $table->id();
                
                $table->foreignId('role_id')
                    ->constrained('roles')
                    ->cascadeOnDelete();
                
                $table->foreignId('permission_id')
                    ->constrained('permissions')
                    ->cascadeOnDelete();
                
                $table->timestamps();
                
                // Unique constraint: each role-permission pair only once
                $table->unique(['role_id', 'permission_id']);
                
                // Indexes for efficient lookups
                $table->index('role_id');
                $table->index('permission_id');
            });
        }
        
        // User-Role pivot table - check if it doesn't already exist (Spatie creates model_has_roles)
        if (!Schema::hasTable('user_roles') && !Schema::hasTable('model_has_roles')) {
            Schema::create('user_roles', function (Blueprint $table) {
                $table->id();
                
                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();
                
                $table->foreignId('role_id')
                    ->constrained('roles')
                    ->cascadeOnDelete();
                
                // Assignment metadata
                $table->foreignId('assigned_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                
                $table->timestamp('assigned_at')->useCurrent();
                $table->timestamp('expires_at')->nullable()->comment('For temporary role assignments');
                
                $table->timestamps();
                
                // Unique constraint: each user-role pair only once
                $table->unique(['user_id', 'role_id']);
                
                // Indexes
                $table->index('user_id');
                $table->index('role_id');
                $table->index('expires_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
