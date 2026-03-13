<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GenericNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $templateKey,
        public string $emailSubject,
        public array $data = []
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        $view = $this->getViewForTemplate($this->templateKey);
        
        return new Content(
            view: $view,
            with: $this->data,
        );
    }

    protected function getViewForTemplate(string $templateKey): string
    {
        $views = [
            'application_submitted' => 'emails.application-submitted',
            'application_approved' => 'emails.application-approved',
            'application_denied' => 'emails.application-denied',
            'additional_info_requested' => 'emails.additional-info-requested',
            'payment_received' => 'emails.payment-received',
            'evisa_ready' => 'emails.evisa-ready',
            'sla_warning' => 'emails.sla-warning',
            'eta_approved' => 'emails.eta-approved',
            'case_assigned' => 'emails.case-assigned',
        ];

        return $views[$templateKey] ?? 'emails.generic';
    }

    public function attachments(): array
    {
        return [];
    }
}
