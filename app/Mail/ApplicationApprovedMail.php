<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\Facades\URL;

class ApplicationApprovedMail extends BaseApplicantMailable
{
    protected static function isCritical(): bool
    {
        return true;
    }

    public function __construct(
        public Application $application
    ) {}

    protected function getApplication(): ?Application
    {
        return $this->application;
    }

    protected function subjectLine(): string
    {
        return "APPROVED — Your Ghana eVisa Has Been Issued — Ref: {$this->application->reference_number}";
    }

    public function content(): Content
    {
        $downloadUrl = URL::temporarySignedRoute(
            'applicant.evisa.download',
            now()->addHours(48),
            ['application' => $this->application->id]
        );
        $visaType = $this->application->visaType?->name ?? 'N/A';
        $validity = $this->application->evisa_file_path ? 'As stated on your eVisa document' : 'As per visa type';

        return new Content(
            view: 'emails.application-approved',
            text: 'emails.application-approved-text',
            with: [
                'applicant_name' => trim($this->application->first_name . ' ' . $this->application->last_name),
                'reference_number' => $this->application->reference_number,
                'visa_type' => $visaType,
                'validity_info' => $validity,
                'download_url' => $downloadUrl,
            ]
        );
    }
}
