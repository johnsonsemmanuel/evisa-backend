<?php

namespace App\Http\Controllers\Api\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\VisaType;
use App\Services\ApplicationService;
use App\Services\EVisaPdfService;
use App\Services\PassportVerificationService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function __construct(
        protected ApplicationService $applicationService,
        protected PaymentService $paymentService,
    ) {}

    /**
     * List all applications for the authenticated applicant.
     */
    public function index(Request $request): JsonResponse
    {
        $applications = $request->user()
            ->applications()
            ->with(['visaType', 'payment'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($applications);
    }

    /**
     * Get available visa types for the applicant.
     */
    public function visaTypes(): JsonResponse
    {
        $types = VisaType::where('is_active', true)
            ->whereIn('slug', ['tourism', 'business']) // Only show Tourism and Business visa types
            ->select('id', 'name', 'slug', 'description', 'base_fee', 'multiple_entry_fee', 'government_fee', 'platform_fee', 'entry_type', 'validity_period', 'category', 'max_duration_days', 'required_documents')
            ->get();

        return response()->json(['visa_types' => $types]);
    }

    /**
     * Public tracking: look up application by reference number and phone/email.
     */
    public function track(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference_number' => 'required|string',
            'identifier'       => 'required|string', // phone or email
        ]);

        $application = Application::where('reference_number', $validated['reference_number'])
            ->where(function ($query) use ($validated) {
                $query->where('email_encrypted', $validated['identifier'])
                      ->orWhere('phone_encrypted', $validated['identifier']);
            })
            ->first();

        if (!$application) {
            return response()->json([
                'message' => __('application.not_found'),
            ], 404);
        }

        return response()->json([
            'reference_number' => $application->reference_number,
            'status'           => $application->status,
            'visa_type'        => $application->visaType->name ?? null,
            'submitted_at'     => $application->submitted_at?->toIso8601String(),
            'decided_at'       => $application->decided_at?->toIso8601String(),
            'timeline'         => $application->statusHistory()->orderBy('created_at', 'asc')->get()->map(fn($h) => [
                'status'     => $h->to_status,
                'changed_at' => $h->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Create a new draft application.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'visa_type_id'   => 'required|exists:visa_types,id',
            'visa_channel'   => 'nullable|string|in:e-visa,regular',
            'entry_type'     => 'nullable|string|in:single,multiple',
            'service_tier_id'=> 'nullable|exists:service_tiers,id',
            'first_name'     => 'required|string|max:255',
            'last_name'      => 'required|string|max:255',
            'date_of_birth'  => 'required|date|before:today',
            'passport_number'=> 'required|string|max:50',
            'passport_issue_date' => 'nullable|date|before_or_equal:today',
            'passport_expiry'=> 'nullable|date|after:today',
            'passport_issuing_authority' => 'nullable|string|max:200',
            'nationality'    => 'required|string|max:3',
            'email'          => 'required|email',
            'phone'          => 'nullable|string|max:20',
            'gender'         => 'nullable|string|in:male,female',
            'marital_status' => 'nullable|string|in:single,married,divorced,widowed,separated',
            'profession'     => 'nullable|string|max:255',
            'country_of_birth' => 'nullable|string|max:3',
            'place_of_birth' => 'nullable|string|max:255',
        ]);

        // Check blacklist
        $visaType = VisaType::findOrFail($validated['visa_type_id']);
        if ($visaType->blacklisted_nationalities &&
            in_array($validated['nationality'], $visaType->blacklisted_nationalities)) {
            return response()->json([
                'message' => __('application.nationality_ineligible'),
            ], 422);
        }

        $application = $this->applicationService->createDraft($validated, $request->user());

        return response()->json([
            'message'     => __('application.draft_created'),
            'application' => $application->load('visaType'),
        ], 201);
    }

    /**
     * Get a single application with full details.
     */
    public function show(Request $request, Application $application): JsonResponse
    {
        $this->authorizeApplicant($request, $application);

        $application->load(['visaType', 'documents', 'payment', 'statusHistory']);

        return response()->json(['application' => $application]);
    }

    /**
     * Update a specific wizard step.
     */
    public function updateStep(Request $request, Application $application): JsonResponse
    {
        $this->authorizeApplicant($request, $application);

        if (!in_array($application->status, ['draft', 'additional_info_requested'])) {
            return response()->json(['message' => __('application.not_editable')], 422);
        }

        $step = $request->validate(['step' => 'required|integer|min:1|max:6'])['step'];

        $application = $this->applicationService->updateStep($application, $step, $request->all());

        return response()->json([
            'message'     => __('application.step_updated'),
            'application' => $application,
        ]);
    }

    /**
     * Update a draft application directly.
     */
    public function update(Request $request, Application $application): JsonResponse
    {
        $this->authorizeApplicant($request, $application);

        if (!in_array($application->status, ['draft', 'additional_info_requested'])) {
            return response()->json(['message' => __('application.not_editable')], 422);
        }

        $validated = $request->validate([
            'first_name'       => 'sometimes|string|max:255',
            'last_name'        => 'sometimes|string|max:255',
            'date_of_birth'    => 'sometimes|date|before:today',
            'passport_number'  => 'sometimes|string|max:50',
            'nationality'      => 'sometimes|string|max:100',
            'email'            => 'sometimes|email',
            'phone'            => 'nullable|string|max:20',
            'intended_arrival' => 'nullable|date|after:today',
            'duration_days'    => 'nullable|integer|min:1|max:365',
            'address_in_ghana' => 'nullable|string|max:500',
            'purpose_of_visit' => 'nullable|string|max:1000',
        ]);

        $application->update($validated);

        return response()->json([
            'message'     => __('application.updated'),
            'application' => $application->fresh()->load('visaType'),
        ]);
    }

    /**
     * Finalize and submit the application (triggers payment flow).
     */
    public function submit(Request $request, Application $application): JsonResponse
    {
        $this->authorizeApplicant($request, $application);

        if (!in_array($application->status, ['paid_submitted', 'pending_payment', 'submitted_awaiting_payment'])) {
            return response()->json(['message' => __('application.not_ready_for_submission')], 422);
        }

        if (!$application->isPaid()) {
            return response()->json(['message' => __('application.payment_required')], 422);
        }

        $application = $this->applicationService->submit($application);

        return response()->json([
            'message'     => __('application.submitted'),
            'application' => $application->load('visaType'),
        ]);
    }

    /**
     * Initiate payment for an application.
     */
    public function initiatePayment(Request $request, Application $application): JsonResponse
    {
        $this->authorizeApplicant($request, $application);

        if (!in_array($application->status, ['draft', 'submitted_awaiting_payment', 'pending_payment'])) {
            return response()->json(['message' => __('application.not_payable')], 422);
        }

        // Transition to submitted_awaiting_payment if still draft
        if ($application->status === 'draft') {
            $application = $this->applicationService->submitForPayment($application);
        }

        $application->update(['status' => 'pending_payment']);
        $result = $this->paymentService->initiatePayment($application);

        return response()->json([
            'message' => __('payment.initiated'),
            'payment' => $result['payment'],
            'checkout_url' => $result['checkout_url'],
            'transaction_reference' => $result['transaction_reference'],
        ]);
    }

    /**
     * Get application status with timeline.
     */
    public function status(Request $request, Application $application): JsonResponse
    {
        $this->authorizeApplicant($request, $application);

        $application->load('statusHistory.changedByUser');

        return response()->json([
            'status'           => $application->status,
            'reference_number' => $application->reference_number,
            'tier'             => $application->tier,
            'sla_hours_left'   => $application->slaHoursRemaining(),
            'timeline'         => $application->statusHistory->map(fn($h) => [
                'status'     => $h->to_status,
                'notes'      => $h->notes,
                'changed_at' => $h->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Download the approved eVisa PDF.
     */
    public function downloadEvisa(Request $request, Application $application): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorizeApplicant($request, $application);

        if (!in_array($application->status, ['approved', 'issued']) || !$application->evisa_file_path) {
            return response()->json(['message' => __('application.evisa_not_available')], 404);
        }

        return response()->streamDownload(function () use ($application) {
            $service = app(EVisaPdfService::class);
            echo $service->download($application);
        }, "eVisa_{$application->reference_number}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Ensure the authenticated user owns this application.
     */
    private function authorizeApplicant(Request $request, Application $application): void
    {
        if ($application->user_id !== $request->user()->id) {
            abort(403, __('auth.unauthorized'));
        }
    }

    /**
     * Get documents for an application (for applicant to view/manage).
     */
    public function documents(Request $request, Application $application): JsonResponse
    {
        $this->authorizeApplicant($request, $application);

        return response()->json([
            'documents' => $application->documents,
        ]);
    }

    /**
     * Download a document for the applicant.
     */
    public function downloadDocument(Request $request, Application $application, \App\Models\ApplicationDocument $document): \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
    {
        $this->authorizeApplicant($request, $application);

        if ($document->application_id !== $application->id) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        $path = storage_path('app/' . $document->stored_path);
        
        if (!file_exists($path)) {
            return response()->json(['message' => 'Document file not found'], 404);
        }

        return response()->streamDownload(function () use ($path) {
            readfile($path);
        }, $document->original_filename, [
            'Content-Type' => $document->mime_type,
            'Content-Disposition' => 'inline; filename="' . $document->original_filename . '"',
        ]);
    }

    /**
     * Delete a document (only for draft/additional_info_requested applications).
     */
    public function deleteDocument(Request $request, Application $application, \App\Models\ApplicationDocument $document): JsonResponse
    {
        $this->authorizeApplicant($request, $application);

        if (!in_array($application->status, ['draft', 'additional_info_requested'])) {
            return response()->json(['message' => 'Cannot modify documents after submission'], 422);
        }

        if ($document->application_id !== $application->id) {
            return response()->json(['message' => 'Document not found'], 404);
        }

        // Delete the file
        $path = storage_path('app/' . $document->stored_path);
        if (file_exists($path)) {
            unlink($path);
        }

        $document->delete();

        return response()->json(['message' => 'Document deleted successfully']);
    }

    /**
     * Submit response to additional information request.
     * Transitions: additional_info_requested → under_review
     */
    public function submitResponse(Request $request, Application $application): JsonResponse
    {
        $this->authorizeApplicant($request, $application);

        if ($application->status !== 'additional_info_requested') {
            return response()->json([
                'message' => 'This application is not awaiting additional information'
            ], 422);
        }

        $validated = $request->validate([
            'message' => 'nullable|string|max:1000',
        ]);

        // Transition back to under_review
        $this->applicationService->changeStatus(
            $application,
            'under_review',
            $validated['message'] ?? 'Applicant has provided the requested additional information'
        );

        // Notify the assigned officer
        \App\Jobs\SendNotification::dispatch($application, 'status_changed', [
            'status' => 'under_review',
            'note' => 'Additional information has been provided by the applicant'
        ]);

        return response()->json([
            'message' => 'Response submitted successfully. Your application is now under review.',
            'application' => $application->fresh(),
        ]);
    }

    /**
     * Delete an application (only draft applications can be deleted).
     */
    public function destroy(Request $request, Application $application): JsonResponse
    {
        $this->authorizeApplicant($request, $application);

        // Only allow deleting draft applications
        if ($application->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft applications can be deleted. This application has already been submitted.'
            ], 422);
        }

        // Delete associated documents
        foreach ($application->documents as $document) {
            $path = storage_path('app/' . $document->stored_path);
            if (file_exists($path)) {
                unlink($path);
            }
            $document->delete();
        }

        // Delete the application
        $application->delete();

        return response()->json(['message' => 'Application deleted successfully']);
    }

    /**
     * Verify passport details.
     */
    public function verifyPassport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'passport_number' => 'required|string|max:20',
            'nationality' => 'required|string|size:2',
            'issue_date' => 'required|date',
            'expiry_date' => 'required|date|after:issue_date',
            'issuing_authority' => 'nullable|string|max:255',
        ]);

        $verificationService = app(PassportVerificationService::class);
        
        $result = $verificationService->verifyPassport($validated);

        return response()->json($result);
    }
}
