{{-- Template for SLA warning notification --}}
@component('mail::message')
# SLA Warning - Action Required

Hello,

Application **{{ $reference_number }}** requires your immediate attention.

**SLA Details:**
- Hours Remaining: {{ $hours_remaining }}
- Deadline: {{ $sla_deadline }}
- Applicant: {{ $applicant_name }}

Please take appropriate action to avoid SLA breach.

Best regards,
Ghana Immigration System
@endcomponent
@endcomponent
