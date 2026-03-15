<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Headers;

class ApplicationReceivedMail extends BaseApplicantMailable
{
    public function __construct(
        public Application $application
    ) {}

    protected function getApplication(): ?Application
    {
        return $this->application;
    }

    protected function subjectLine(): string
    {
        return "Ghana eVisa Application Received — Ref: {$this->application->reference_number}";
    }

    public function content(): Content
    {
        $visaType = $this->application->visaType?->name ?? 'N/A';
        $arrival = $this->application->intended_arrival?->format('d M Y');
        $duration = $this->application->duration_days ? (string) $this->application->duration_days . ' days' : null;
        $travelDates = $arrival && $duration ? "{$arrival} ({$duration})" : ($arrival ?? 'As submitted');
        $tier = $this->application->tier ?? $this->application->processing_tier ?? 'Standard';

        return new Content(
            view: 'emails.application-received',
            text: 'emails.application-received-text',
            with: [
                'applicant_name' => trim($this->application->first_name . ' ' . $this->application->last_name),
                'reference_number' => $this->application->reference_number,
                'visa_type' => $visaType,
                'travel_dates' => $travelDates,
                'processing_tier' => $tier,
                'estimated_processing' => '5–10 business days',
            ]
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'X-Application-Ref' => $this->application->reference_number,
        ]);
    }
}
