{{-- Template for case_assigned notification --}}
@component('mail::message')
# New Case Assignment

Hello {{ $officer_name }},

A new visa application has been assigned to you:

**Application Details:**
- Reference: {{ $reference_number }}
- Visa Type: {{ $visa_type }}
- Applicant: {{ $applicant_name }}

Please review this application at your earliest convenience.

Best regards,
Ghana Immigration Service
@endcomponent
@endcomponent
