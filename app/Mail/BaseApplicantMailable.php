<?php

namespace App\Mail;

use App\Models\Application;
use App\Models\FailedEmailNotification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

abstract class BaseApplicantMailable extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300];
    public string $queue = 'mail';

    /**
     * Whether this email is critical (approval, rejection, eVisa issued).
     * If true and delivery fails, assigned officer is notified to contact applicant by phone.
     */
    protected static function isCritical(): bool
    {
        return false;
    }

    abstract protected function getApplication(): ?Application;

    public function envelope(): Envelope
    {
        $bcc = config('mail.compliance_bcc') ? [config('mail.compliance_bcc')] : [];
        return new Envelope(
            subject: $this->subjectLine(),
            bcc: $bcc,
        );
    }

    abstract protected function subjectLine(): string;

    /**
     * Called when the queued send fails (hooked via AppServiceProvider Queue::failing).
     * Log with application_id only (no recipient PII). Create FailedEmailNotification.
     * If critical: notify assigned officer to contact applicant by phone.
     */
    public function failed(Throwable $e): void
    {
        $application = $this->getApplication();
        $applicationId = $application?->id;

        Log::error('Applicant email failed', [
            'mailable' => static::class,
            'application_id' => $applicationId,
        ]);

        FailedEmailNotification::create([
            'mailable_class' => static::class,
            'application_id' => $applicationId,
            'attempted_at' => now(),
            'failure_reason' => $e->getMessage(),
        ]);

        if (static::isCritical() && $application) {
            $this->notifyOfficerToContactApplicant($application);
        }
    }

    protected function notifyOfficerToContactApplicant(Application $application): void
    {
        $officer = $application->assignedOfficer ?? $application->approvalOfficer ?? $application->reviewingOfficer;
        if (!$officer) {
            $officers = User::query()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['gis_officer', 'mfa_reviewer', 'visa_officer', 'admin']))
                ->limit(1)
                ->get();
            $officer = $officers->first();
        }
        if ($officer) {
            try {
                Notification::send($officer, new \App\Notifications\CriticalEmailFailedNotification(
                    $application->reference_number,
                    static::class
                ));
            } catch (Throwable $e) {
                Log::warning('Could not notify officer of critical email failure', [
                    'application_id' => $application->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
