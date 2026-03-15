<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Mail\Mailables\Content;

class ApplicationRejectedMail extends BaseApplicantMailable
{
    /** Predefined reason labels (no free text from officers). */
    public const REASON_LABELS = [
        'INCOMPLETE_DOCUMENTATION' => 'Incomplete or insufficient documentation was provided.',
        'INELIGIBLE_NATIONALITY' => 'Applicant nationality is not eligible for this visa type.',
        'SECURITY_CONCERN' => 'The application could not be approved for security reasons.',
        'TRAVEL_HISTORY' => 'Travel history requirements were not met.',
        'PURPOSE_NOT_ESTABLISHED' => 'Purpose of visit could not be satisfactorily established.',
    ];

    public function __construct(
        public Application $application,
        /** @var array<int, string> Reason codes from reason_codes table or predefined codes above */
        public array $reasonCodes = []
    ) {}

    protected static function isCritical(): bool
    {
        return true;
    }

    protected function getApplication(): ?Application
    {
        return $this->application;
    }

    protected function subjectLine(): string
    {
        return "Ghana eVisa Application — Decision Notice — Ref: {$this->application->reference_number}";
    }

    public function content(): Content
    {
        $reasons = $this->reasonCodes;
        $labels = [];
        foreach ($reasons as $code) {
            $labels[] = self::REASON_LABELS[$code] ?? $code;
        }
        if (empty($labels) && $this->application->denial_reason_codes) {
            $codes = is_array($this->application->denial_reason_codes)
                ? $this->application->denial_reason_codes
                : [];
            foreach ($codes as $code) {
                $labels[] = self::REASON_LABELS[$code] ?? $code;
            }
        }
        if (empty($labels)) {
            $labels[] = 'The application did not meet the requirements for approval.';
        }

        return new Content(
            view: 'emails.application-rejected',
            text: 'emails.application-rejected-text',
            with: [
                'applicant_name' => trim($this->application->first_name . ' ' . $this->application->last_name),
                'reference_number' => $this->application->reference_number,
                'reason_labels' => $labels,
                'refund_info' => 'If you have already paid, refund information will be sent separately where applicable.',
            ]
        );
    }
}
