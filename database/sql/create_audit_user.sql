-- Create restricted audit database user
-- ISO 27001 A.8.15 compliance - immutable audit logs
-- 
-- This user has INSERT and SELECT privileges only on audit_logs and audit_checksums tables.
-- No UPDATE or DELETE privileges, ensuring audit logs cannot be modified or deleted at the database level.
--
-- DEPLOYMENT INSTRUCTIONS:
-- 1. Run this script as MySQL root user
-- 2. Update .env with DB_AUDIT_USER and DB_AUDIT_PASSWORD
-- 3. Test connection: php artisan tinker -> DB::connection('audit')->select('SELECT 1')

-- Create the audit user (change password in production)
CREATE USER IF NOT EXISTS 'evisa_audit_ro'@'localhost' IDENTIFIED BY 'change_me_in_production';
CREATE USER IF NOT EXISTS 'evisa_audit_ro'@'%' IDENTIFIED BY 'change_me_in_production';

-- Grant INSERT and SELECT only on audit tables
GRANT INSERT, SELECT ON evisa_system.audit_logs TO 'evisa_audit_ro'@'localhost';
GRANT INSERT, SELECT ON evisa_system.audit_logs TO 'evisa_audit_ro'@'%';

GRANT INSERT, SELECT ON evisa_system.audit_checksums TO 'evisa_audit_ro'@'localhost';
GRANT INSERT, SELECT ON evisa_system.audit_checksums TO 'evisa_audit_ro'@'%';

-- Apply privileges
FLUSH PRIVILEGES;

-- Verify privileges (should show only INSERT and SELECT)
SHOW GRANTS FOR 'evisa_audit_ro'@'localhost';
