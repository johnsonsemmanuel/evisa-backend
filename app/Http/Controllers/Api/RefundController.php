<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RefundApprovalRequest;
use App\Http\Requests\RefundRequest as RefundFormRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\User;
use App\Notifications\RefundApprovedNotification;
use App\Notifications\RefundInitiatedNotification;
use App\Notifications\RefundRejectedNotification;
use App\Services\AuthService;
use App\Services\GcbPaymentService;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class RefundController extends Controller
{
    use ApiResponse;

    protected GcbPaymentService $gcbService;
    protected PaystackService $paystackService;

    public function __construct(
        GcbPaymentService $gcbService,
        PaystackService $paystackService,
        protected AuthService $authService
    ) {
        $this->gcbService = $gcbService;
        $this->paystackService = $paystackService;
    }

    /**
     * Initiate a refund request.
     */
    public function initiate(RefundFormRequest $request): JsonResponse
    {
        // SECURITY: Check token has required ability for refund creation
        $this->authService->requireAbility(auth()->user(), 'refund:create');

        // Check authorization policy
        Gate::authorize('initiate', RefundRequest::class);

        try {
            $payment = $request->getPayment();

            if (!$payment) {
                return $this->error('Payment not found', 404);
            }

            // Check if payment is already refunded
            $existingRefund = RefundRequest::where('payment_id', $payment->id)
                ->whereIn('status', ['approved', 'processing', 'processed'])
                ->first();

            if ($existingRefund) {
                return $this->error('This payment has already been refunded or has a pending refund', 400);
            }

            DB::beginTransaction();

            try {
                // Create refund request
                $refundRequest = RefundRequest::create([
                    'payment_id' => $payment->id,
                    'application_id' => $payment->application_id,
                    'gateway' => $payment->gateway,
                    'amount' => $request->input('amount'),
                    'reason' => $request->input('reason'),
                    'attachments' => $request->input('attachments'),
                    'initiated_by' => auth()->id(),
                    'initiated_at' => now(),
                    'status' => 'pending_approval',
                ]);

                // Check if dual approval is required
                if ($refundRequest->requiresDualApproval()) {
                    // Amount > GHS 500 requires second approval
                    $refundRequest->update([
                        'status' => 'awaiting_second_approval',
                    ]);

                    // Notify all finance officers except the initiator
                    $this->notifyFinanceOfficers($refundRequest, 'awaiting_approval');

                    DB::commit();

                    Log::info('Refund request created - awaiting second approval', [
                        'refund_request_id' => $refundRequest->id,
                        'payment_id' => $payment->id,
                        'amount' => $refundRequest->amount,
                        'initiated_by' => auth()->id(),
                    ]);

                    return $this->success([
                        'refund_request' => $refundRequest->load(['payment', 'application', 'initiator']),
                        'message' => 'Refund request created. Awaiting approval from another finance officer (amount > GHS 500).',
                    ], 201);
                } else {
                    // Amount <= GHS 500 - auto-approve and process immediately
                    $refundRequest->update([
                        'status' => 'approved',
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                    ]);

                    // Process the refund immediately
                    $result = $this->processRefund($refundRequest);

                    DB::commit();

                    if ($result['success']) {
                        Log::info('Refund auto-approved and processed', [
                            'refund_request_id' => $refundRequest->id,
                            'payment_id' => $payment->id,
                            'amount' => $refundRequest->amount,
                        ]);

                        return $this->success([
                            'refund_request' => $refundRequest->fresh()->load(['payment', 'application', 'initiator']),
                            'message' => 'Refund approved and processed successfully (amount ≤ GHS 500).',
                        ], 201);
                    } else {
                        return $this->error('Refund approved but processing failed: ' . $result['message'], 500);
                    }
                }
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Refund initiation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return $this->error('Failed to initiate refund: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Approve a refund request.
     */
    public function approve(RefundApprovalRequest $request, RefundRequest $refundRequest): JsonResponse
    {
        // SECURITY: Check token has required ability for refund approval
        $this->authService->requireAbility(auth()->user(), 'refund:approve');

        // Check authorization policy
        Gate::authorize('approve', $refundRequest);

        try {
            DB::beginTransaction();

            try {
                // Update refund request
                $refundRequest->update([
                    'status' => 'approved',
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                ]);

                // Process the refund
                $result = $this->processRefund($refundRequest);

                DB::commit();

                if ($result['success']) {
                    // Notify initiator
                    $refundRequest->initiator->notify(new RefundApprovedNotification($refundRequest));

                    Log::info('Refund approved and processed', [
                        'refund_request_id' => $refundRequest->id,
                        'approved_by' => auth()->id(),
                        'gateway_reference' => $refundRequest->gateway_refund_reference,
                    ]);

                    return $this->success([
                        'refund_request' => $refundRequest->fresh()->load(['payment', 'application', 'initiator', 'approver']),
                        'message' => 'Refund approved and processed successfully.',
                    ]);
                } else {
                    return $this->error('Refund approved but processing failed: ' . $result['message'], 500);
                }
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Refund approval failed', [
                'refund_request_id' => $refundRequest->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return $this->error('Failed to approve refund: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reject a refund request.
     */
    public function reject(Request $request, RefundRequest $refundRequest): JsonResponse
    {
        // Check authorization
        Gate::authorize('reject', $refundRequest);

        // Validate rejection reason
        $request->validate([
            'rejection_reason' => 'required|string|min:20|max:500',
        ]);

        try {
            DB::beginTransaction();

            try {
                // Update refund request
                $refundRequest->update([
                    'status' => 'rejected',
                    'rejected_by' => auth()->id(),
                    'rejected_at' => now(),
                    'rejection_reason' => $request->input('rejection_reason'),
                ]);

                DB::commit();

                // Notify initiator
                $refundRequest->initiator->notify(new RefundRejectedNotification($refundRequest));

                Log::info('Refund rejected', [
                    'refund_request_id' => $refundRequest->id,
                    'rejected_by' => auth()->id(),
                    'reason' => $request->input('rejection_reason'),
                ]);

                return $this->success([
                    'refund_request' => $refundRequest->fresh()->load(['payment', 'application', 'initiator', 'rejector']),
                    'message' => 'Refund request rejected.',
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            Log::error('Refund rejection failed', [
                'refund_request_id' => $refundRequest->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return $this->error('Failed to reject refund: ' . $e->getMessage(), 500);
        }
    }

    /**
     * List refund requests.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', RefundRequest::class);

        $query = RefundRequest::with(['payment', 'application', 'initiator', 'approver', 'rejector']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by gateway
        if ($request->has('gateway')) {
            $query->forGateway($request->input('gateway'));
        }

        // Filter by initiator
        if ($request->has('initiated_by')) {
            $query->initiatedBy($request->input('initiated_by'));
        }

        // Filter pending approvals (exclude own requests)
        if ($request->boolean('pending_my_approval')) {
            $query->awaitingSecondApproval()
                  ->notInitiatedBy(auth()->id());
        }

        $refunds = $query->latest()->paginate(20);

        return $this->success($refunds);
    }

    /**
     * Show a specific refund request.
     */
    public function show(RefundRequest $refundRequest): JsonResponse
    {
        Gate::authorize('view', $refundRequest);

        $refundRequest->load(['payment', 'application', 'initiator', 'approver', 'rejector']);

        return $this->success($refundRequest);
    }

    /**
     * Process the refund through the gateway.
     */
    protected function processRefund(RefundRequest $refundRequest): array
    {
        try {
            $refundRequest->update(['status' => 'processing']);

            $payment = $refundRequest->payment;

            // Call the appropriate gateway service
            $result = match ($refundRequest->gateway) {
                'gcb' => $this->gcbService->processRefund(
                    $payment,
                    $refundRequest->amount,
                    $refundRequest->reason
                ),
                'paystack' => $this->paystackService->processRefund(
                    $payment,
                    $refundRequest->amount
                ),
                default => ['success' => false, 'message' => 'Unknown gateway'],
            };

            if ($result['success']) {
                $refundRequest->update([
                    'status' => 'processed',
                    'processed_at' => now(),
                    'gateway_refund_reference' => $result['reference'] ?? null,
                    'gateway_response' => $result,
                ]);

                // Update payment status if full refund
                if ($refundRequest->amount >= $payment->amount) {
                    $payment->update(['status' => 'refunded']);
                }

                return ['success' => true, 'message' => 'Refund processed successfully'];
            } else {
                $refundRequest->update([
                    'status' => 'failed',
                    'gateway_response' => $result,
                ]);

                return ['success' => false, 'message' => $result['message'] ?? 'Refund processing failed'];
            }
        } catch (\Exception $e) {
            $refundRequest->update([
                'status' => 'failed',
                'gateway_response' => ['error' => $e->getMessage()],
            ]);

            Log::error('Refund processing exception', [
                'refund_request_id' => $refundRequest->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Notify finance officers about a refund request.
     */
    protected function notifyFinanceOfficers(RefundRequest $refundRequest, string $type): void
    {
        $financeOfficers = User::whereHas('roles', function ($query) {
            $query->where('name', 'finance_officer');
        })->where('id', '!=', $refundRequest->initiated_by)->get();

        if ($financeOfficers->isEmpty()) {
            Log::warning('No finance officers found to notify about refund request', [
                'refund_request_id' => $refundRequest->id,
            ]);
            return;
        }

        Notification::send($financeOfficers, new RefundInitiatedNotification($refundRequest));
    }
}
