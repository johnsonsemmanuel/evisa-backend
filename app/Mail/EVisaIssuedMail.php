<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;

class EVisaIssuedMail extends BaseApplicantMailable
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
        return "Your Ghana eVisa is Ready — Ref: {$this->application->reference_number}";
    }

    public function content(): Content
    {
        $downloadUrl = URL::temporarySignedRoute(
            'applicant.evisa.download',
            now()->addDays(7),
            ['application' => $this->application->id]
        );
        $qrPath = $this->application->evisa_qr_code
            ? (is_string($this->application->evisa_qr_code) ? $this->application->evisa_qr_code : null)
            : null;
        $qrUrl = $qrPath && Storage::exists($qrPath) ? Storage::url($qrPath) : null;
        $visaType = $this->application->visaType?->name ?? 'N/A';

        return new Content(
            view: 'emails.evisa-issued',
            text: 'emails.evisa-issued-text',
            with: [
                'applicant_name' => trim($this->application->first_name . ' ' . $this->application->last_name),
                'reference_number' => $this->application->reference_number,
                'download_url' => $downloadUrl,
                'download_expiry_days' => 7,
                'qr_url' => $qrUrl,
                'visa_type' => $visaType,
            ]
        );
    }
}
