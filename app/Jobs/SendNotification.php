<?php

namespace App\Jobs;

use App\Models\Application;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

class SendNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 15;

    public function __construct(
        public Application $application,
        public string $type,
        public array $data = [],
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        Log::info("Sending notification [{$this->type}] for application {$this->application->reference_number}");

        switch ($this->type) {
            case 'application_submitted':
                $this->notifyApplicant('Your visa application has been submitted successfully.');
                break;

            case 'new_application_assigned':
                $this->notifyAgencyOfficers("New application {$this->application->reference_number} assigned for review.");
                break;

            case 'status_changed':
                $statusLabel = $this->data['status'] ?? $this->application->status;
                $this->notifyApplicant("Your application status has been updated to: {$statusLabel}");
                break;

            case 'sla_warning':
                $hours = $this->data['hours_remaining'] ?? '?';
                $this->notifyAgencyOfficers("SLA Warning: Application {$this->application->reference_number} has {$hours}h remaining.");
                break;

            case 'sla_breached':
                $this->notifyAgencyOfficers("SLA BREACHED: Application {$this->application->reference_number} has exceeded its deadline.");
                break;

            case 'document_reupload_required':
                $docType = $this->data['document_type'] ?? 'document';
                $reason = $this->data['reason'] ?? '';
                $this->notifyApplicant("Please re-upload your {$docType}: {$reason}");
                break;

            case 'application_approved':
                $this->notifyApplicant('Your visa application has been approved! You can now download your eVisa.');
                $this->sendEmail('emails.application-approved');
                break;

            case 'visa_issued':
                $this->notifyApplicant('Your eVisa has been issued and is ready for download. Please check your dashboard.');
                $this->sendEmail('emails.visa-issued');
                break;

            case 'application_denied':
                $this->notifyApplicant('Your visa application has been denied. Please check your dashboard for details.');
                $this->sendEmail('emails.application-denied');
                break;

            default:
                Log::warning("Unknown notification type: {$this->type}");
        }
    }

    /**
     * Send notification to the applicant who owns this application.
     */
    protected function notifyApplicant(string $message): void
    {
        $user = $this->application->user;
        if ($user) {
            // Store as database notification
            $user->notify(new \App\Notifications\ApplicationNotification(
                $this->application,
                $this->type,
                $message,
                $this->data
            ));

            Log::info("Notified applicant {$user->email}: {$message}");
        }
    }

    /**
     * Send an actual email to the applicant using a blade template.
     */
    protected function sendEmail(string $view): void
    {
        $user = $this->application->user;
        if (!$user || !$user->email) {
            return;
        }

        try {
            $data = [
                'applicant_name' => $user->full_name ?? ($user->first_name . ' ' . $user->last_name),
                'reference_number' => $this->application->reference_number,
                'visa_type' => $this->application->visaType?->name ?? 'Visa',
                'status' => $this->application->status,
                'application' => $this->application,
            ];

            Mail::send($view, $data, function ($mail) use ($user) {
                $mail->to($user->email, $user->full_name ?? $user->first_name)
                     ->subject($this->getEmailSubject())
                     ->from(config('mail.from.address', 'noreply@gis.gov.gh'), 'Ghana eVisa Portal');
            });

            Log::info("Email sent to {$user->email} for [{$this->type}]");
        } catch (\Throwable $e) {
            Log::error("Failed to send email to {$user->email}: {$e->getMessage()}");
        }
    }

    /**
     * Get email subject based on notification type.
     */
    protected function getEmailSubject(): string
    {
        return match ($this->type) {
            'application_approved' => 'Your Ghana eVisa Has Been Approved! - ' . $this->application->reference_number,
            'visa_issued' => 'Your Ghana eVisa Is Ready for Download - ' . $this->application->reference_number,
            'application_denied' => 'Ghana eVisa Application Update - ' . $this->application->reference_number,
            'application_submitted' => 'Application Received - ' . $this->application->reference_number,
            'additional_info_requested' => 'Action Required - ' . $this->application->reference_number,
            default => 'Ghana eVisa Update - ' . $this->application->reference_number,
        };
    }

    /**
     * Send notification to all officers in the assigned agency.
     */
    protected function notifyAgencyOfficers(string $message): void
    {
        $agency = $this->application->assigned_agency;
        $roleMap = ['gis' => 'gis_officer', 'mfa' => 'mfa_reviewer'];
        $role = $roleMap[$agency] ?? null;

        if (!$role) {
            return;
        }

        $officers = User::where('role', $role)->where('is_active', true)->get();

        foreach ($officers as $officer) {
            $officer->notify(new \App\Notifications\ApplicationNotification(
                $this->application,
                $this->type,
                $message,
                $this->data
            ));
        }

        Log::info("Notified {$officers->count()} {$agency} officers: {$message}");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("Failed to send notification [{$this->type}] for {$this->application->reference_number}: {$exception->getMessage()}");
    }
}
