<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for TOTP-based Multi-Factor Authentication.
    | Required by NIST SP 800-53 IA-2 for government systems.
    |
    */

    /**
     * Roles that REQUIRE 2FA (mandatory enrollment)
     */
    'required_roles' => [
        'super_admin',
        'admin',
        'gis_officer',
        'gis_reviewer',
        'gis_approver',
        'gis_admin',
        'GIS_REVIEWING_OFFICER',
        'GIS_APPROVAL_OFFICER',
        'GIS_ADMIN',
        'mfa_officer',
        'mfa_reviewer',
        'mfa_approver',
        'mfa_admin',
        'MFA_REVIEWING_OFFICER',
        'MFA_APPROVAL_OFFICER',
        'MFA_ADMIN',
        'visa_officer',
        'finance_officer',
        'border_officer',
    ],

    /**
     * Roles that can OPTIONALLY enable 2FA
     */
    'optional_roles' => [
        'applicant',
    ],

    /**
     * TOTP Settings
     */
    'totp' => [
        // Issuer name shown in authenticator apps
        'issuer' => env('APP_NAME', 'Ghana eVisa'),
        
        // Time window for TOTP codes (30 seconds is standard)
        'window' => 30,
        
        // Number of time windows to check (1 = current + 1 past + 1 future)
        'tolerance' => 1,
        
        // Secret key length (16 bytes = 128 bits)
        'secret_length' => 16,
    ],

    /**
     * Recovery Codes Settings
     */
    'recovery_codes' => [
        // Number of recovery codes to generate
        'count' => 8,
        
        // Format: XXXX-XXXX-XXXX (12 characters + 2 hyphens)
        'length' => 12,
        
        // Character set for recovery codes
        'characters' => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789', // Excludes ambiguous: 0, O, I, 1
    ],

    /**
     * Two-Factor Token Settings (temporary token during login)
     */
    'token' => [
        // Expiration time for two-factor tokens (5 minutes)
        'expires_in' => 300, // seconds
        
        // Redis key prefix
        'prefix' => '2fa_token:',
        
        // Token length
        'length' => 64,
    ],

    /**
     * Rate Limiting
     */
    'rate_limit' => [
        // Maximum verification attempts per user per hour
        'max_attempts' => 5,
        
        // Lockout duration after max attempts (minutes)
        'lockout_duration' => 60,
    ],

    /**
     * QR Code Settings
     */
    'qr_code' => [
        // QR code size in pixels
        'size' => 200,
        
        // Error correction level (L, M, Q, H)
        'error_correction' => 'M',
    ],

];
