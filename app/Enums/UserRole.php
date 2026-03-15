<?php

namespace App\Enums;

enum UserRole: string
{
    case Applicant = 'applicant';
    case GisOfficer = 'gis_officer';
    case GisReviewer = 'gis_reviewer';
    case GisApprover = 'gis_approver';
    case GisAdmin = 'gis_admin';
    case MfaReviewer = 'mfa_reviewer';
    case MfaApprover = 'mfa_approver';
    case MfaAdmin = 'mfa_admin';
    case Admin = 'admin';
    case ImmigrationOfficer = 'immigration_officer';
    case AirlineStaff = 'airline_staff';

    public function label(): string
    {
        return match($this) {
            self::Applicant => 'Applicant',
            self::GisOfficer => 'GIS Officer',
            self::GisReviewer => 'GIS Reviewer',
            self::GisApprover => 'GIS Approver',
            self::GisAdmin => 'GIS Administrator',
            self::MfaReviewer => 'MFA Reviewer',
            self::MfaApprover => 'MFA Approver',
            self::MfaAdmin => 'MFA Administrator',
            self::Admin => 'System Administrator',
            self::ImmigrationOfficer => 'Immigration Officer',
            self::AirlineStaff => 'Airline Staff',
        };
    }

    public function agency(): ?AgencyType
    {
        return match($this) {
            self::GisOfficer,
            self::GisReviewer,
            self::GisApprover,
            self::GisAdmin => AgencyType::GIS,
            
            self::MfaReviewer,
            self::MfaApprover,
            self::MfaAdmin => AgencyType::MFA,
            
            default => null,
        };
    }

    public function canReviewApplications(): bool
    {
        return in_array($this, [
            self::GisReviewer,
            self::GisApprover,
            self::GisAdmin,
            self::MfaReviewer,
            self::MfaApprover,
            self::MfaAdmin,
            self::Admin
        ]);
    }

    public function canApproveApplications(): bool
    {
        return in_array($this, [
            self::GisApprover,
            self::GisAdmin,
            self::MfaApprover,
            self::MfaAdmin,
            self::Admin
        ]);
    }

    public function canAccessBorderSystem(): bool
    {
        return in_array($this, [
            self::ImmigrationOfficer,
            self::AirlineStaff,
            self::Admin
        ]);
    }

    public function isStaff(): bool
    {
        return $this !== self::Applicant;
    }
}