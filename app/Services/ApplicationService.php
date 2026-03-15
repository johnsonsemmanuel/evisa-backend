<?php

namespace App\Services;

use App\Jobs\SendNotification;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ApplicationService
{
    public function __construct(
        protected ApplicationRoutingService $routingService,
    ) {}

    /**
     * Create a new draft application for the authenticated applicant.
     * Per specification: TAID is generated immediately after eligibility check
     */
    public function createDraft(array $data, User $user): Application
    {
        // Decrypt PII if encrypted (data may come encrypted from EncryptsPii trait)
        $passportNumber = $data['passport_number'];
        $nationality = $data['nationality'];
        
        // Check if values are encrypted (encrypted strings are much longer)
        if (is_string($passportNumber) && strlen($passportNumber) > 50) {
            try {
                $passportNumber = \Illuminate\Support\Facades\Crypt::decryptString($passportNumber);
            } catch (\Exception $e) {
                // Already plain text
            }
        }
        
        if (is_string($nationality) && strlen($nationality) > 2) {
            try {
                $nationality = \Illuminate\Support\Facades\Crypt::decryptString($nationality);
            } catch (\Exception $e) {
                // Already plain text
            }
        }

        // Generate TAID with plain text values for lookups
        $travelAuth = \App\Models\TravelAuthorization::createTaid(
            $passportNumber,
            $nationality,
            'VISA'
        );

        $application = Application::create([
            'reference_number'       => Application::generateReferenceNumber(),
            'taid'                   => $travelAuth->taid,
            'user_id'                => $user->id,
            'visa_type_id'           => $data['visa_type_id'],
            'visa_channel'           => $data['visa_channel'] ?? 'e-visa',
            'entry_type'             => $data['entry_type'] ?? 'single',
            'service_tier_id'        => $data['service_tier_id'] ?? null,
            'first_name_encrypted'   => $data['first_name'],
            'last_name_encrypted'    => $data['last_name'],
            'date_of_birth_encrypted'=> $data['date_of_birth'],
            'passport_number_encrypted' => $data['passport_number'],
            'nationality_encrypted'  => $data['nationality'],
            'email_encrypted'        => $data['email'],
            'phone_encrypted'        => $data['phone'] ?? null,
            'gender'                 => $data['gender'] ?? null,
            'marital_status'         => $data['marital_status'] ?? null,
            'profession_encrypted'   => $data['profession'] ?? null,
            'country_of_birth'       => $data['country_of_birth'] ?? $data['place_of_birth'] ?? null,
            'passport_issue_date'    => $data['passport_issue_date'] ?? null,
            'passport_expiry'        => $data['passport_expiry'] ?? null,
            'intended_arrival'       => $data['intended_arrival'] ?? null,
            'duration_days'          => $data['duration_days'] ?? null,
            'address_in_ghana'       => $data['address_in_ghana'] ?? null,
            'purpose_of_visit'       => $data['purpose_of_visit'] ?? null,
            'status'                 => 'draft',
            'current_step'           => 1,
        ]);

        $this->recordStatusChange($application, null, 'draft', 'Application created with TAID: ' . $travelAuth->taid);

        return $application;
    }

    /**
     * Update a draft application's step data.
     */
    public function updateStep(Application $application, int $step, array $data): Application
    {
        $fillable = [];

        switch ($step) {
            case 1: // Visa Category (channel, type, entry, tier)
                if (isset($data['visa_channel'])) $fillable['visa_channel'] = $data['visa_channel'];
                if (isset($data['entry_type'])) $fillable['entry_type'] = $data['entry_type'];
                if (isset($data['service_tier_id'])) $fillable['service_tier_id'] = $data['service_tier_id'];
                break;

            case 2: // Personal Details
                $fillable = [
                    'first_name_encrypted'      => $data['first_name'] ?? $application->first_name_encrypted,
                    'last_name_encrypted'        => $data['last_name'] ?? $application->last_name_encrypted,
                    'date_of_birth_encrypted'    => $data['date_of_birth'] ?? $application->date_of_birth_encrypted,
                    'passport_number_encrypted'  => $data['passport_number'] ?? $application->passport_number_encrypted,
                    'nationality_encrypted'      => $data['nationality'] ?? $application->nationality_encrypted,
                    'email_encrypted'            => $data['email'] ?? $application->email_encrypted,
                    'phone_encrypted'            => $data['phone'] ?? $application->phone_encrypted,
                    'gender'                     => $data['gender'] ?? $application->gender,
                    'marital_status'             => $data['marital_status'] ?? $application->marital_status,
                    'profession_encrypted'       => $data['profession'] ?? $application->profession_encrypted,
                    'country_of_birth'           => $data['country_of_birth'] ?? $application->country_of_birth,
                    'passport_issue_date'        => $data['passport_issue_date'] ?? $application->passport_issue_date,
                    'passport_expiry'            => $data['passport_expiry'] ?? $application->passport_expiry,
                ];
                break;

            case 3: // Travel Info
                $fillable = [
                    'intended_arrival'  => $data['intended_arrival'] ?? $application->intended_arrival,
                    'duration_days'     => $data['duration_days'] ?? $application->duration_days,
                    'visa_duration'     => $data['visa_duration'] ?? $application->visa_duration,
                    'address_in_ghana'  => $data['address_in_ghana'] ?? $application->address_in_ghana,
                    'purpose_of_visit'  => $data['purpose_of_visit'] ?? $application->purpose_of_visit,
                ];
                
                // Dynamically save any field passed in Step 3 that exists on the model
                $step3DynamicFields = [
                    'port_of_entry', 'destination_city', 'accommodation_type', 
                    'visited_other_countries', 'visited_country_1', 'visited_country_2', 'visited_country_3'
                ];
                foreach ($step3DynamicFields as $f) {
                    if (isset($data[$f])) $fillable[$f] = $data[$f];
                }
                if (isset($data['passport_expiry'])) $fillable['passport_expiry'] = $data['passport_expiry'];
                break;

            case 4: // Documents (handled via DocumentService)
                break;

            case 5: // Declaration
                break;

            case 6: // Review & Submit
                break;
        }

        $fillable['current_step'] = $step;
        $application->update($fillable);

        return $application->fresh();
    }

    /**
     * Submit the application for payment.
     * Transitions: draft -> submitted_awaiting_payment
     */
    public function submitForPayment(Application $application): Application
    {
        $fromStatus = $application->status;
        $application->status = 'submitted_awaiting_payment';
        $application->submitted_at = now();
        $application->save();

        $this->recordStatusChange($application, $fromStatus, 'submitted_awaiting_payment', 'Application submitted, awaiting payment');

        SendNotification::dispatch($application, 'status_changed', ['status' => 'submitted_awaiting_payment']);

        return $application;
    }

    /**
     * Confirm payment for an application.
     * Transitions: submitted_awaiting_payment/pending_payment → paid_submitted
     * Does NOT trigger routing — routing is handled separately.
     */
    public function confirmPayment(Application $application): Application
    {
        if (!in_array($application->status, ['submitted_awaiting_payment', 'pending_payment'])) {
            return $application;
        }

        $fromStatus = $application->status;
        $application->status = 'paid_submitted';
        if (!$application->submitted_at) {
            $application->submitted_at = now();
        }
        $application->save();

        $this->recordStatusChange($application, $fromStatus, 'paid_submitted', 'Payment confirmed');

        SendNotification::dispatch($application, 'status_changed', ['status' => 'paid_submitted']);

        return $application;
    }

    /**
     * Submit an application after payment is confirmed.
     * Routes through CPH for tier classification and agency assignment.
     * SECURITY: Auto-rejects blacklisted nationalities.
     */
    public function submit(Application $application): Application
    {
        // Check for blacklisted nationalities
        $visaType = $application->visaType;
        if ($visaType && !empty($visaType->blacklisted_nationalities)) {
            $blacklisted = $visaType->blacklisted_nationalities;
            if (in_array($application->nationality, $blacklisted)) {
                $application->status = 'denied';
                $application->decided_at = now();
                $application->decision_notes = 'Application automatically denied: nationality not eligible for this visa type.';
                $application->save();
                
                $this->changeStatus($application, 'denied', 'Auto-denied: blacklisted nationality');
                return $application;
            }
        }

        $fromStatus = $application->status;
        $application->status = 'submitted';
        if (!$application->submitted_at) {
            $application->submitted_at = now();
        }
        $application->save();

        $this->recordStatusChange($application, $fromStatus, 'submitted', 'Application submitted with payment');

        // Dispatch routing through CPH
        $this->routingService->route($application);
        $this->recordStatusChange($application, 'submitted', $application->status, "Routed to {$application->assigned_agency} as {$application->tier}");

        // Send notifications
        SendNotification::dispatch($application, 'application_submitted');
        SendNotification::dispatch($application, 'new_application_assigned');

        return $application;
    }

    /**
     * Valid status transitions matrix.
     * Each key is a source status, and its value is an array of valid target statuses.
     */
    protected const VALID_TRANSITIONS = [
        'draft'                      => ['submitted_awaiting_payment', 'cancelled'],
        'submitted_awaiting_payment' => ['pending_payment', 'paid_submitted', 'cancelled'],
        'pending_payment'            => ['paid_submitted', 'cancelled'],
        'paid_submitted'             => ['submitted', 'cancelled'],
        'submitted'                  => ['under_review', 'pending_approval', 'additional_info_requested', 'denied', 'cancelled'],
        'under_review'               => ['pending_approval', 'additional_info_requested', 'escalated'],
        'additional_info_requested'  => ['under_review', 'cancelled'],
        'escalated'                  => ['under_review', 'pending_approval', 'additional_info_requested'],
        'pending_approval'           => ['approved', 'denied', 'additional_info_requested', 'under_review', 'escalated'],
        'approved'                   => ['issued', 'under_review'],
        'denied'                     => ['under_review'],
        'issued'                     => [],
        'cancelled'                  => [], // Cancelled applications cannot go back to draft
    ];

    /**
     * Check if a status transition is valid.
     */
    public function isValidTransition(string $from, string $to): bool
    {
        $allowed = self::VALID_TRANSITIONS[$from] ?? [];
        return in_array($to, $allowed);
    }

    /**
     * Update application status with full audit trail.
     * Validates the transition against the allowed transition matrix.
     */
    public function changeStatus(Application $application, string $newStatus, ?string $notes = null): Application
    {
        $fromStatus = $application->status;

        if (!$this->isValidTransition($fromStatus, $newStatus)) {
            throw new \InvalidArgumentException(
                "Invalid status transition: {$fromStatus} → {$newStatus}"
            );
        }

        $application->status = $newStatus;

        if (in_array($newStatus, ['approved', 'denied'])) {
            $application->decided_at = now();
            $application->decision_notes = $notes;
        }

        // Queue management: clear queue on terminal statuses, restore on revert
        if (in_array($newStatus, ['approved', 'denied', 'issued', 'cancelled'])) {
            $application->current_queue = null;
        } elseif ($newStatus === 'under_review' && in_array($fromStatus, ['approved', 'denied', 'pending_approval', 'escalated', 'additional_info_requested'])) {
            $application->current_queue = 'review_queue';
        }

        $application->save();
        $this->recordStatusChange($application, $fromStatus, $newStatus, $notes);

        // Broadcast real-time status change
        app(\App\Services\RealTimeDashboardService::class)->broadcastApplicationStatusChange(
            $application, $fromStatus, $newStatus, $notes
        );

        // Send appropriate notifications based on status change
        if ($newStatus === 'approved') {
            SendNotification::dispatch($application, 'application_approved');
        } elseif ($newStatus === 'denied') {
            SendNotification::dispatch($application, 'application_denied');
        } elseif ($newStatus === 'issued') {
            SendNotification::dispatch($application, 'visa_issued');
        } elseif ($newStatus === 'additional_info_requested') {
            SendNotification::dispatch($application, 'document_reupload_required', ['reason' => $notes]);
        } else {
            SendNotification::dispatch($application, 'status_changed', ['status' => $newStatus]);
        }

        return $application;
    }

    /**
     * Record a status transition in the history log.
     */
    protected function recordStatusChange(
        Application $application,
        ?string $from,
        string $to,
        ?string $notes = null
    ): void {
        ApplicationStatusHistory::create([
            'application_id' => $application->id,
            'changed_by'     => Auth::id(),
            'from_status'    => $from,
            'to_status'      => $to,
            'notes'          => $notes,
            'ip_address'     => Request::ip(),
        ]);
    }
}
