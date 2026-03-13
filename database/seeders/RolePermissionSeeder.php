<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Permissions ──────────────────────────────────────

        $permissions = [
            // Application permissions
            'applications.view',
            'applications.view_own',
            'applications.create',
            'applications.review',
            'applications.approve',
            'applications.deny',
            'applications.escalate',
            'applications.request_info',
            'applications.assign',
            'applications.reassign',

            // Document permissions
            'documents.view',
            'documents.upload',
            'documents.verify',

            // User management
            'users.view',
            'users.create',
            'users.edit',
            'users.deactivate',

            // Mission management
            'missions.view',
            'missions.manage',

            // System administration
            'system.configure',
            'system.audit_logs',
            'system.manage_roles',

            // Reporting
            'reports.view',
            'reports.export',

            // Border control
            'border.verify',
            'border.admit',
            'border.deny',
            'border.secondary_inspection',
            'border.override',

            // Risk & watchlist
            'risk.view',
            'risk.assess',
            'watchlist.manage',

            // Internal notes
            'notes.view',
            'notes.create',

            // eVisa generation
            'evisa.generate',
            'evisa.revoke',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // ── Roles ────────────────────────────────────────────

        // GIS Roles
        $gisAdmin = Role::firstOrCreate(['name' => 'GIS_ADMIN', 'guard_name' => 'web']);
        $gisAdmin->syncPermissions([
            'applications.view', 'applications.review', 'applications.approve', 'applications.deny',
            'applications.escalate', 'applications.request_info', 'applications.assign', 'applications.reassign',
            'documents.view', 'documents.verify',
            'users.view', 'users.create', 'users.edit', 'users.deactivate',
            'missions.view',
            'system.configure', 'system.audit_logs', 'system.manage_roles',
            'reports.view', 'reports.export',
            'risk.view', 'risk.assess', 'watchlist.manage',
            'notes.view', 'notes.create',
            'evisa.generate', 'evisa.revoke',
        ]);

        $gisReviewing = Role::firstOrCreate(['name' => 'GIS_REVIEWING_OFFICER', 'guard_name' => 'web']);
        $gisReviewing->syncPermissions([
            'applications.view', 'applications.review',
            'applications.escalate', 'applications.request_info',
            'documents.view', 'documents.verify',
            'risk.view', 'risk.assess',
            'notes.view', 'notes.create',
            'reports.view',
        ]);

        $gisApproval = Role::firstOrCreate(['name' => 'GIS_APPROVAL_OFFICER', 'guard_name' => 'web']);
        $gisApproval->syncPermissions([
            'applications.view', 'applications.review', 'applications.approve', 'applications.deny',
            'applications.escalate', 'applications.request_info',
            'documents.view', 'documents.verify',
            'risk.view', 'risk.assess',
            'notes.view', 'notes.create',
            'reports.view', 'reports.export',
            'evisa.generate',
        ]);

        // MFA Roles
        $mfaAdmin = Role::firstOrCreate(['name' => 'MFA_ADMIN', 'guard_name' => 'web']);
        $mfaAdmin->syncPermissions([
            'applications.view', 'applications.review', 'applications.approve', 'applications.deny',
            'applications.escalate', 'applications.request_info', 'applications.assign', 'applications.reassign',
            'documents.view', 'documents.verify',
            'users.view', 'users.create', 'users.edit', 'users.deactivate',
            'missions.view', 'missions.manage',
            'system.audit_logs',
            'reports.view', 'reports.export',
            'risk.view',
            'notes.view', 'notes.create',
        ]);

        $mfaReviewing = Role::firstOrCreate(['name' => 'MFA_REVIEWING_OFFICER', 'guard_name' => 'web']);
        $mfaReviewing->syncPermissions([
            'applications.view', 'applications.review',
            'applications.escalate', 'applications.request_info',
            'documents.view', 'documents.verify',
            'risk.view',
            'notes.view', 'notes.create',
            'reports.view',
        ]);

        $mfaApproval = Role::firstOrCreate(['name' => 'MFA_APPROVAL_OFFICER', 'guard_name' => 'web']);
        $mfaApproval->syncPermissions([
            'applications.view', 'applications.review', 'applications.approve', 'applications.deny',
            'applications.escalate', 'applications.request_info',
            'documents.view', 'documents.verify',
            'risk.view',
            'notes.view', 'notes.create',
            'reports.view', 'reports.export',
            'evisa.generate',
        ]);

        // Applicant role (for completeness)
        $applicant = Role::firstOrCreate(['name' => 'APPLICANT', 'guard_name' => 'web']);
        $applicant->syncPermissions([
            'applications.view_own', 'applications.create',
            'documents.upload',
        ]);

        // Border roles
        $borderOfficer = Role::firstOrCreate(['name' => 'BORDER_OFFICER', 'guard_name' => 'web']);
        $borderOfficer->syncPermissions([
            'border.verify', 'border.admit', 'border.deny', 'border.secondary_inspection',
        ]);

        $borderSupervisor = Role::firstOrCreate(['name' => 'BORDER_SUPERVISOR', 'guard_name' => 'web']);
        $borderSupervisor->syncPermissions([
            'border.verify', 'border.admit', 'border.deny', 'border.secondary_inspection', 'border.override',
            'reports.view',
        ]);

        // System admin (super role)
        $sysAdmin = Role::firstOrCreate(['name' => 'SYSTEM_ADMIN', 'guard_name' => 'web']);
        $sysAdmin->syncPermissions(Permission::all());
    }
}
