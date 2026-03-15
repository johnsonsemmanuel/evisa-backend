<?php

namespace App\Jobs;

use App\Enums\KycStatus;
use App\Models\User;
use App\Services\SumsubService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ProcessSumsubWebhook implements ShouldQueue
{
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [30, 120, 300];

    public function __construct(
        public array $payload
    ) {}

    public function handle(SumsubService $sumsub): void
    {
        $type = $this->payload['type'] ?? null;
        $applicantId = $this->payload['applicantId'] ?? null;
        $externalUserId = $this->payload['externalUserId'] ?? null;
        $reviewStatus = $this->payload['reviewStatus'] ?? null;
        $reviewResult = $this->payload['reviewResult'] ?? [];

        $user = $this->resolveUser($applicantId, $externalUserId);
        if (!$user) {
            Log::warning('ProcessSumsubWebhook: user not found', [
                'applicant_id' => $applicantId,
                'external_user_id' => $externalUserId,
            ]);
            return;
        }

        switch ($type) {
            case 'applicantReviewed':
                $this->handleApplicantReviewed($user, $reviewResult, $sumsub);
                break;
            case 'applicantPending':
                $user->update([
                    'kyc_status' => KycStatus::UnderReview,
                ]);
                Log::info('Sumsub webhook: applicant pending', ['user_id' => $user->id]);
                break;
            case 'applicantOnHold':
                $user->update([
                    'kyc_status' => KycStatus::OnHold,
                ]);
                $this->notifyGisOfficerOnHold($user);
                Log::info('Sumsub webhook: applicant on hold', ['user_id' => $user->id]);
                break;
            case 'applicantDeleted':
                Log::info('Sumsub webhook: applicant deleted', ['user_id' => $user->id, 'applicant_id' => $applicantId]);
                break;
            default:
                Log::info('Sumsub webhook: unhandled type', ['type' => $type, 'user_id' => $user->id]);
        }
    }

    private function resolveUser(?string $applicantId, ?string $externalUserId): ?User
    {
        if ($externalUserId !== null && $externalUserId !== '') {
            $user = User::withoutGlobalScopes()->find((int) $externalUserId);
            if ($user) {
                return $user;
            }
        }
        if ($applicantId !== null && $applicantId !== '') {
            return User::withoutGlobalScopes()->where('sumsub_applicant_id', $applicantId)->first();
        }
        return null;
    }

    private function handleApplicantReviewed(User $user, array $reviewResult, SumsubService $sumsub): void
    {
        $answer = $reviewResult['reviewAnswer'] ?? $reviewResult['reviewAnswer'] ?? null;
        $kycStatus = $sumsub->mapReviewAnswerToKycStatus($answer);
        $rejectionLabels = $reviewResult['rejectLabels'] ?? $reviewResult['rejectionLabels'] ?? [];

        $user->update([
            'kyc_status' => $kycStatus,
            'kyc_completed_at' => $kycStatus->isFinal() ? now() : null,
            'kyc_rejection_labels' => $kycStatus === KycStatus::Rejected ? $rejectionLabels : null,
        ]);

        if ($kycStatus === KycStatus::Approved) {
            $this->allowPaymentStepForUser($user);
            Log::info('Sumsub webhook: applicant approved', ['user_id' => $user->id]);
        }

        if ($kycStatus === KycStatus::Rejected) {
            $this->notifyApplicantRejected($user, $rejectionLabels);
            Log::info('Sumsub webhook: applicant rejected', ['user_id' => $user->id, 'labels' => $rejectionLabels]);
        }
    }

    private function allowPaymentStepForUser(User $user): void
    {
        $application = $user->applications()
            ->whereIn('status', ['submitted', 'under_review'])
            ->latest()
            ->first();
        if ($application) {
            $application->update(['status' => \App\Enums\ApplicationStatus::PendingPayment]);
        }
    }

    private function notifyApplicantRejected(User $user, array $labels): void
    {
        try {
            $notification = new \App\Notifications\KycRejectedNotification($user, $labels);
            $user->notify($notification);
        } catch (Throwable $e) {
            Log::warning('KycRejectedNotification failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    private function notifyGisOfficerOnHold(User $user): void
    {
        $email = config('security.monitoring.alert_email') ?? config('mail.from.address');
        if (!$email) {
            return;
        }
        try {
            Notification::route('mail', $email)->notify(
                new \App\Notifications\KycOnHoldNotification($user)
            );
        } catch (Throwable $e) {
            Log::warning('KycOnHoldNotification failed', ['error' => $e->getMessage()]);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::error('ProcessSumsubWebhook failed', [
            'payload_type' => $this->payload['type'] ?? null,
            'applicant_id' => $this->payload['applicantId'] ?? null,
            'error' => $e->getMessage(),
        ]);
    }
}
