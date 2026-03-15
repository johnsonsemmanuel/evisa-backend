<?php

namespace App\Mail;

use App\Models\Application;
use App\Models\ApplicationDocument;
use Illuminate\Mail\Mailables\Content;

class AdditionalDocumentsRequestedMail extends BaseApplicantMailable
{
    /** Document types or descriptions to request (from Document model / document_type). */
    public function __construct(
        public Application $application,
        public array $documentDescriptions = []
    ) {}

    protected function getApplication(): ?Application
    {
        return $this->application;
    }

    protected function subjectLine(): string
    {
        return "Action Required — Additional Documents Needed — Ref: {$this->application->reference_number}";
    }

    public function content(): Content
    {
        $descriptions = $this->documentDescriptions;
        if (empty($descriptions)) {
            $docs = $this->application->documents()->where('verification_status', 'reupload_requested')->get();
            foreach ($docs as $doc) {
                $descriptions[] = $doc->document_type ?? 'Document (' . $doc->id . ')';
            }
            if (empty($descriptions)) {
                $descriptions[] = 'Additional documents as specified in your applicant portal.';
            }
        }
        $loginUrl = rtrim(config('app.frontend_url', config('app.url')), '/') . '/login';
        $deadlineDays = 14;
        $deadlineDate = now()->addDays($deadlineDays)->format('d F Y');

        return new Content(
            view: 'emails.additional-documents-requested',
            text: 'emails.additional-documents-requested-text',
            with: [
                'applicant_name' => trim($this->application->first_name . ' ' . $this->application->last_name),
                'reference_number' => $this->application->reference_number,
                'document_descriptions' => $descriptions,
                'deadline_days' => $deadlineDays,
                'deadline_date' => $deadlineDate,
                'login_url' => $loginUrl,
            ]
        );
    }
}
