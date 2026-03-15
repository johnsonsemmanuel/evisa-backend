<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add critical performance indexes based on query pattern analysis.
     * These indexes target the most frequent query patterns in the eVisa platform.
     */
    public function up(): void
    {
        // ==========================================
        // APPLICATIONS TABLE - Critical Indexes
        // ==========================================
        
        Schema::table('applications', function (Blueprint $table) {
            // 1. GIS Officer Queue - Most critical query (100+ times/day)
            // Query: WHERE assigned_agency='gis' AND status IN (...) ORDER BY sla_deadline
            try {
                $table->index(['assigned_agency', 'status', 'sla_deadline'], 'idx_apps_agency_status_sla');
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            // 2. Applicant Dashboard - High frequency (500+ times/day)
            // Query: WHERE user_id=? AND status=? ORDER BY created_at DESC
            try {
                $table->index(['user_id', 'status', 'created_at'], 'idx_apps_user_status_created');
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            // 3. Analytics Date Range Queries - Medium frequency (50+ times/day)
            // Query: WHERE created_at >= ? AND status IN (...)
            try {
                $table->index(['created_at', 'status'], 'idx_apps_created_status');
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            // 4. Officer Assignment Queries
            // Query: WHERE assigned_officer_id=? AND status IN (...)
            try {
                $table->index(['assigned_officer_id', 'status'], 'idx_apps_officer_status');
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            // 5. Reference Number Search - High frequency (200+ times/day)
            // Query: WHERE reference_number LIKE '%...%'
            try {
                $table->index('reference_number', 'idx_apps_reference_number');
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            // 6. SLA Monitoring Queries
            // Query: WHERE sla_deadline <= NOW() AND status IN (...)
            try {
                $table->index(['sla_deadline', 'status'], 'idx_apps_sla_status');
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            // 7. Batch Processing Queries
            // Query: WHERE assigned_agency=? AND status IN (...) AND assigned_officer_id IS NULL
            try {
                $table->index(['assigned_agency', 'assigned_officer_id', 'status'], 'idx_apps_agency_officer_status');
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            // 8. Decided Applications Analytics
            // Query: WHERE decided_at BETWEEN ? AND ? AND status IN (...)
            try {
                $table->index(['decided_at', 'status'], 'idx_apps_decided_status');
            } catch (\Exception $e) {
                // Index might already exist
            }
        });

        // ==========================================
        // PAYMENTS TABLE - Revenue & Reconciliation
        // ==========================================
        
        Schema::table('payments', function (Blueprint $table) {
            // 1. Revenue Analytics - High frequency (50+ times/day)
            // Query: WHERE status='paid' AND paid_at BETWEEN ? AND ?
            try {
                $table->index(['status', 'paid_at'], 'idx_payments_status_paid_at');
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            // 2. Webhook Processing - Critical for real-time (1000+ times/day)
            // Query: WHERE transaction_reference=? AND payment_provider=?
            try {
                $table->index(['transaction_reference', 'payment_provider'], 'idx_payments_ref_provider');
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            // 3. Reconciliation Queries - Medium frequency (20+ times/day)
            // Query: WHERE gateway=? AND status=? AND created_at >= ?
            try {
                $table->index(['gateway', 'status', 'created_at'], 'idx_payments_gateway_status_created');
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            // 4. Application Payment History
            // Query: WHERE application_id=? ORDER BY created_at DESC
            try {
                $table->index(['application_id', 'created_at'], 'idx_payments_app_created');
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            // 5. User Payment History
            // Query: WHERE user_id=? AND status=? ORDER BY created_at DESC
            try {
                $table->index(['user_id', 'status', 'created_at'], 'idx_payments_user_status_created');
            } catch (\Exception $e) {
                // Index might already exist
            }
        });

        // ==========================================
        // USERS TABLE - Authentication & Management
        // ==========================================
        
        Schema::table('users', function (Blueprint $table) {
            // 1. Admin User Management
            // Query: WHERE role=? AND agency=? AND is_active=?
            try {
                $table->index(['role', 'agency', 'is_active'], 'idx_users_role_agency_active');
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            // 2. Officer Assignment Lookups
            // Query: WHERE agency_id=? AND is_active=true
            try {
                $table->index(['agency_id', 'is_active'], 'idx_users_agency_active');
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            // 3. Mission-based Queries (MFA officers)
            // Query: WHERE mission_id=? AND is_active=true
            try {
                $table->index(['mission_id', 'is_active'], 'idx_users_mission_active');
            } catch (\Exception $e) {
                // Index might already exist
            }
        });

        // ==========================================
        // AUDIT_LOGS TABLE - Compliance & Monitoring
        // ==========================================
        
        Schema::table('audit_logs', function (Blueprint $table) {
            // 1. Application Timeline - High frequency (100+ times/day)
            // Query: WHERE application_id=? ORDER BY created_at ASC
            try {
                $table->index(['application_id', 'created_at'], 'idx_audit_app_created');
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            // 2. User Activity Monitoring
            // Query: WHERE user_id=? AND created_at >= ? ORDER BY created_at DESC
            try {
                $table->index(['user_id', 'created_at'], 'idx_audit_user_created');
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            // 3. Action-based Queries
            // Query: WHERE action LIKE '%approve%' AND created_at >= ?
            try {
                $table->index(['action', 'created_at'], 'idx_audit_action_created');
            } catch (\Exception $e) {
                // Index might already exist
            }
        });

        // ==========================================
        // APPLICATION_DOCUMENTS TABLE - Document Management
        // ==========================================
        
        Schema::table('application_documents', function (Blueprint $table) {
            // 1. Document Verification Queries
            // Query: WHERE application_id=? AND document_type=?
            try {
                $table->index(['application_id', 'document_type'], 'idx_docs_app_type');
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            // 2. Verification Status Queries
            // Query: WHERE verification_status=? AND created_at >= ?
            try {
                $table->index(['verification_status', 'created_at'], 'idx_docs_status_created');
            } catch (\Exception $e) {
                // Index might already exist
            }
        });

        // ==========================================
        // RISK_SCORES TABLE - Risk Management
        // ==========================================
        
        if (Schema::hasTable('risk_scores')) {
            Schema::table('risk_scores', function (Blueprint $table) {
                // 1. Current Risk Score Lookup - Critical (500+ times/day)
                // Query: WHERE application_id=? AND is_current=true
                try {
                    $table->index(['application_id', 'is_current'], 'idx_risk_app_current');
                } catch (\Exception $e) {
                    // Index might already exist
                }
                
                // 2. High Risk Applications
                // Query: WHERE risk_level IN ('high', 'critical') AND is_current=true
                try {
                    $table->index(['risk_level', 'is_current'], 'idx_risk_level_current');
                } catch (\Exception $e) {
                    // Index might already exist
                }
                
                // 3. Risk Score History
                // Query: WHERE application_id=? ORDER BY assessed_at DESC
                try {
                    $table->index(['application_id', 'assessed_at'], 'idx_risk_app_assessed');
                } catch (\Exception $e) {
                    // Index might already exist
                }
            });
        }

        // ==========================================
        // BORDER_VERIFICATIONS TABLE - Entry Control
        // ==========================================
        
        if (Schema::hasTable('border_verifications')) {
            Schema::table('border_verifications', function (Blueprint $table) {
                // 1. Port-based Queries
                // Query: WHERE port_of_entry=? AND verified_at >= ?
                try {
                    $table->index(['port_of_entry', 'verified_at'], 'idx_border_port_verified');
                } catch (\Exception $e) {
                    // Index might already exist
                }
                
                // 2. Entry Status Reports
                // Query: WHERE entry_status=? AND verified_at BETWEEN ? AND ?
                try {
                    $table->index(['entry_status', 'verified_at'], 'idx_border_status_verified');
                } catch (\Exception $e) {
                    // Index might already exist
                }
                
                // 3. Aeropass Hit Analysis
                // Query: WHERE aeropass_checked=true AND aeropass_result='hit'
                try {
                    $table->index(['aeropass_checked', 'aeropass_result'], 'idx_border_aeropass');
                } catch (\Exception $e) {
                    // Index might already exist
                }
            });
        }

        // ==========================================
        // INTERNAL_NOTES TABLE - Case Management
        // ==========================================
        
        Schema::table('internal_notes', function (Blueprint $table) {
            // 1. Application Notes Timeline
            // Query: WHERE application_id=? ORDER BY created_at DESC
            try {
                $table->index(['application_id', 'created_at'], 'idx_notes_app_created');
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            // 2. Officer Activity
            // Query: WHERE user_id=? AND created_at >= ?
            try {
                $table->index(['user_id', 'created_at'], 'idx_notes_user_created');
            } catch (\Exception $e) {
                // Index might already exist
            }
        });

        // ==========================================
        // VISA_TYPES TABLE - Configuration Lookups
        // ==========================================
        
        Schema::table('visa_types', function (Blueprint $table) {
            // 1. Active Visa Types Lookup
            // Query: WHERE is_active=true AND category=?
            try {
                $table->index(['is_active', 'category'], 'idx_visa_types_active_category');
            } catch (\Exception $e) {
                // Index might already exist
            }
            
            // 2. Slug-based Lookups
            // Query: WHERE slug=? AND is_active=true
            try {
                $table->index(['slug', 'is_active'], 'idx_visa_types_slug_active');
            } catch (\Exception $e) {
                // Index might already exist
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes in reverse order
        
        Schema::table('visa_types', function (Blueprint $table) {
            $table->dropIndex('idx_visa_types_active_category');
            $table->dropIndex('idx_visa_types_slug_active');
        });
        
        Schema::table('internal_notes', function (Blueprint $table) {
            $table->dropIndex('idx_notes_app_created');
            $table->dropIndex('idx_notes_user_created');
        });
        
        if (Schema::hasTable('border_verifications')) {
            Schema::table('border_verifications', function (Blueprint $table) {
                $table->dropIndex('idx_border_port_verified');
                $table->dropIndex('idx_border_status_verified');
                $table->dropIndex('idx_border_aeropass');
            });
        }
        
        if (Schema::hasTable('risk_scores')) {
            Schema::table('risk_scores', function (Blueprint $table) {
                $table->dropIndex('idx_risk_app_current');
                $table->dropIndex('idx_risk_level_current');
                $table->dropIndex('idx_risk_app_assessed');
            });
        }
        
        Schema::table('application_documents', function (Blueprint $table) {
            $table->dropIndex('idx_docs_app_type');
            $table->dropIndex('idx_docs_status_created');
        });
        
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_app_created');
            $table->dropIndex('idx_audit_user_created');
            $table->dropIndex('idx_audit_action_created');
        });
        
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_role_agency_active');
            $table->dropIndex('idx_users_agency_active');
            $table->dropIndex('idx_users_mission_active');
        });
        
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_status_paid_at');
            $table->dropIndex('idx_payments_ref_provider');
            $table->dropIndex('idx_payments_gateway_status_created');
            $table->dropIndex('idx_payments_app_created');
            $table->dropIndex('idx_payments_user_status_created');
        });
        
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex('idx_apps_agency_status_sla');
            $table->dropIndex('idx_apps_user_status_created');
            $table->dropIndex('idx_apps_created_status');
            $table->dropIndex('idx_apps_officer_status');
            $table->dropIndex('idx_apps_reference_number');
            $table->dropIndex('idx_apps_sla_status');
            $table->dropIndex('idx_apps_agency_officer_status');
            $table->dropIndex('idx_apps_decided_status');
        });
    }
};