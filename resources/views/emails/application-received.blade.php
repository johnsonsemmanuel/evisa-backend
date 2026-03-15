@extends('emails.layout')

@section('title', 'Application Received — Ref: ' . ($reference_number ?? ''))

@section('content')
<h2>Ghana eVisa Application Received</h2>

<p>Dear {{ $applicant_name }},</p>

<p>Thank you for submitting your visa application. The Ministry of Foreign Affairs has received your application and it will be processed in accordance with the Government of Ghana's procedures.</p>

<div class="reference-box">
    <p style="margin: 0 0 4px 0; font-size: 12px; color: #666;">Your Application Reference Number</p>
    <p class="reference-number">{{ $reference_number }}</p>
    <p style="margin: 8px 0 0; font-size: 12px; color: #666;">Keep this reference for all correspondence and tracking.</p>
</div>

<div class="info-box">
    <strong>Application summary</strong>
    <ul style="margin: 8px 0 0; padding-left: 20px;">
        <li>Visa type: {{ $visa_type ?? 'N/A' }}</li>
        <li>Travel dates: {{ $travel_dates ?? 'As submitted' }}</li>
        <li>Processing tier: {{ $processing_tier ?? 'Standard' }}</li>
    </ul>
</div>

<h3>What happens next</h3>
<ol>
    <li>Your application will be reviewed by the relevant authorities.</li>
    <li>Estimated processing time: {{ $estimated_processing ?? '5–10 business days' }}.</li>
    <li>If additional documents are required, you will be notified by email with instructions.</li>
    <li>Once approved, you will receive your eVisa and payment instructions if applicable.</li>
</ol>

<p><strong>Important:</strong> Retain this reference number for all future correspondence regarding this application.</p>

<p>For enquiries, please contact: <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a></p>
@endsection
