<?php

namespace App\Services;

use App\Models\Application;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    protected array $templates = [
        'application_submitted' => [
            'subject' => 'Visa Application Submitted - {reference_number}',
            'template' => 'emails.application-submitted',
        ],
        'application_approved' => [
            'subject' => 'Visa Application Approved - {reference_number}',
            'template' => 'emails.application-approved',
        ],
        'application_denied' => [
            'subject' => 'Visa Application Decision - {reference_number}',
            'template' => 'emails.application-denied',
        ],
        'additional_info_requested' => [
            'subject' => 'Additional Information Required - {reference_number}',
            'template' => 'emails.additional-info-requested',
        ],
        'payment_received' => [
            'subject' => 'Payment Confirmed - {reference_number}',
            'template' => 'emails.payment-received',
        ],
        'evisa_ready' => [
            'subject' => 'Your eVisa is Ready - {reference_number}',
            'template' => 'emails.evisa-ready',
        ],
        'sla_warning' => [
            'subject' => 'SLA Warning: Application {reference_number}',
            'template' => 'emails.sla-warning',
        ],
        'eta_approved' => [
            'subject' => 'ETA Approved - {reference_number}',
            'template' => 'emails.eta-approved',
        ],
        'case_assigned' => [
            'subject' => 'New Case Assigned: {reference_number}',
            'template' => 'emails.case-assigned',
        ],
    ];

    /**
     * Send notification for application status change.
     */
    public function notifyApplicationStatus(Application $application, string $status, array $extraData = []): ?NotificationLog
    {
        $templateKey = $this->getTemplateKeyForStatus($status);
        if (!$templateKey) {
            return null;
        }

        // Create database notification for real-time display
        $this->createDatabaseNotification($application, $templateKey, $status, $extraData);

        $email = $application->email;
        if (!$email) {
            Log::warning("No email found for application {$application->reference_number}");
            return null;
        }

        return $this->sendEmail(
            $email,
            $templateKey,
            array_merge([
                'application' => $application,
                'reference_number' => $application->reference_number,
                'applicant_name' => $application->first_name . ' ' . $application->last_name,
                'visa_type' => $application->visaType?->name,
                'status' => $status,
            ], $extraData),
            $application->id,
            $application->user_id
        );
    }

    /**
     * Send email notification.
     */
    public function sendEmail(
        string $recipient,
        string $templateKey,
        array $data = [],
        ?int $applicationId = null,
        ?int $userId = null
    ): NotificationLog {
        $template = $this->templates[$templateKey] ?? null;
        
        if (!$template) {
            throw new \InvalidArgumentException("Unknown template: {$templateKey}");
        }

        $subject = $this->interpolate($template['subject'], $data);

        // Create notification log
        $log = NotificationLog::create([
            'application_id' => $applicationId,
            'user_id' => $userId,
            'channel' => 'email',
            'template' => $templateKey,
            'recipient' => $recipient,
            'subject' => $subject,
            'metadata' => $data,
            'status' => 'pending',
            'provider' => config('mail.default'),
        ]);

        // Send email asynchronously via queue
        try {
            Mail::to($recipient)->queue(
                new \App\Mail\GenericNotification($templateKey, $subject, $data)
            );
            
            $log->markAsSent();
            Log::info("Email queued: {$templateKey} to {$recipient}");
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            Log::error("Email send failed: {$e->getMessage()}");
        }

        return $log;
    }

    /**
     * Send SMS notification.
     */
    public function sendSms(
        string $phoneNumber,
        string $message,
        ?int $applicationId = null,
        ?int $userId = null
    ): NotificationLog {
        $log = NotificationLog::create([
            'application_id' => $applicationId,
            'user_id' => $userId,
            'channel' => 'sms',
            'recipient' => $phoneNumber,
            'content' => $message,
            'status' => 'pending',
            'provider' => config('services.sms.provider', 'twilio'),
        ]);

        // Basic SMS integration (can be extended with actual provider)
        try {
            // For now, log SMS as sent (production: integrate with Twilio/Africa's Talking)
            // Example: Twilio integration would be:
            // $twilio = new \Twilio\Rest\Client(config('services.twilio.sid'), config('services.twilio.token'));
            // $twilio->messages->create($phoneNumber, [
            //     'from' => config('services.twilio.from'),
            //     'body' => $message
            // ]);
            
            $log->markAsSent();
            Log::info("SMS logged as sent to {$phoneNumber} (provider integration needed)");
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());
            Log::error("SMS send failed: {$e->getMessage()}");
        }

        return $log;
    }

    /**
     * Notify officer of new case assignment.
     */
    public function notifyOfficerAssignment(Application $application, User $officer): ?NotificationLog
    {
        return $this->sendEmail(
            $officer->email,
            'case_assigned',
            [
                'officer_name' => $officer->first_name,
                'reference_number' => $application->reference_number,
                'visa_type' => $application->visaType?->name,
                'applicant_name' => $application->first_name . ' ' . $application->last_name,
                'application' => $application,
            ],
            $application->id,
            $officer->id
        );
    }

    /**
     * Notify about SLA breach or warning.
     */
    public function notifySlaWarning(Application $application, int $hoursRemaining): ?NotificationLog
    {
        $officer = $application->assignedOfficer;
        if (!$officer) {
            return null;
        }

        return $this->sendEmail(
            $officer->email,
            'sla_warning',
            [
                'reference_number' => $application->reference_number,
                'hours_remaining' => $hoursRemaining,
                'sla_deadline' => $application->sla_deadline?->format('Y-m-d H:i'),
                'applicant_name' => $application->first_name . ' ' . $application->last_name,
            ],
            $application->id,
            $officer->id
        );
    }

    /**
     * Create database notification for real-time display.
     */
    protected function createDatabaseNotification(Application $application, string $type, string $status, array $extraData = []): void
    {
        $user = $application->user;
        if (!$user) {
            Log::warning("No user found for application {$application->reference_number}");
            return;
        }

        $message = $this->getNotificationMessage($type, $application);
        
        $user->notify(new \App\Notifications\ApplicationStatusChanged(
            $application,
            $type,
            $message
        ));
    }

    /**
     * Get notification message for type.
     */
    protected function getNotificationMessage(string $type, Application $application): string
    {
        return match ($type) {
            'application_submitted' => "Your visa application has been submitted successfully.",
            'application_approved' => "Congratulations! Your visa application has been approved.",
            'application_denied' => "Your visa application has been denied.",
            'additional_info_requested' => "Additional information is required for your application.",
            'payment_received' => "Your payment has been received and confirmed.",
            'evisa_ready' => "Your eVisa is ready for download.",
            'status_changed' => "Your application status has been updated to: {$application->status}",
            default => "Your application has been updated.",
        };
    }

    /**
     * Get template key for application status.
     */
    protected function getTemplateKeyForStatus(string $status): ?string
    {
        return match ($status) {
            'submitted' => 'application_submitted',
            'approved' => 'application_approved',
            'denied' => 'application_denied',
            'additional_info_requested' => 'additional_info_requested',
            default => null,
        };
    }

    /**
     * Interpolate variables in string.
     */
    protected function interpolate(string $template, array $data): string
    {
        foreach ($data as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $template = str_replace('{' . $key . '}', (string) $value, $template);
            }
        }
        return $template;
    }

    /**
     * Retry failed notifications.
     */
    public function retryFailed(int $maxRetries = 3): array
    {
        $failed = NotificationLog::failed()
            ->where('retry_count', '<', $maxRetries)
            ->get();

        $results = ['retried' => 0, 'success' => 0, 'failed' => 0];

        foreach ($failed as $log) {
            $results['retried']++;
            
            try {
                if ($log->channel === 'email') {
                    Mail::to($log->recipient)->queue(
                        new \App\Mail\GenericNotification(
                            $log->template,
                            $log->subject,
                            $log->metadata ?? []
                        )
                    );
                    $log->markAsSent();
                    $results['success']++;
                }
            } catch (\Exception $e) {
                $log->markAsFailed($e->getMessage());
                $results['failed']++;
            }
        }

        return $results;
    }
}
