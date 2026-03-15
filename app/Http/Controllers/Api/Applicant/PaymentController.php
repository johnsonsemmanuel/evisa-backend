<?php

namespace App\Http\Controllers\Api\Applicant;

use App\Enums\PaymentGateway;
use App\Exceptions\PaymentNotAllowedException;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Payment;
use App\Services\ApplicationService;
use App\Services\MultiPaymentService;
use App\Services\PaymentGatewayService;
use App\Services\VisaFeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected ApplicationService $applicationService;
    protected MultiPaymentService $paymentService;
    protected PaymentGatewayService $gatewayService;
    protected VisaFeeService $feeService;

    public function __construct(
        ApplicationService $applicationService,
        MultiPaymentService $paymentService,
        PaymentGatewayService $gatewayService,
        VisaFeeService $feeService
    ) {
        $this->applicationService = $applicationService;
        $this->paymentService = $paymentService;
        $this->gatewayService = $gatewayService;
        $this->feeService = $feeService;
    }
    /**
     * Initiate payment for an application.
     *
     * SECURITY CRITICAL: Amount is ALWAYS calculated server-side.
     * Client-supplied amounts are REJECTED.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function initiate(Request $request): JsonResponse
    {
        // Validate request - ONLY accept application_id, gateway, and currency
        // gateway: gcb | paystack | auto (GCB-first fallback) | bank_transfer (legacy)
        // REJECT any client-supplied amount, fee, or total
        $validated = $request->validate([
            'application_id' => 'required|integer|exists:applications,id',
            'gateway' => 'required|string|in:gcb,paystack,auto,bank_transfer',
            'currency' => 'nullable|string|in:GHS,USD',
            'callback_url' => 'nullable|url',
        ]);

        // SECURITY: Explicitly reject client-supplied amounts
        if ($request->has(['amount', 'fee', 'total', 'total_fee'])) {
            Log::warning('Payment initiation attempted with client-supplied amount', [
                'user_id' => $request->user()->id,
                'application_id' => $validated['application_id'],
                'rejected_fields' => array_keys($request->only(['amount', 'fee', 'total', 'total_fee'])),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Amount must be calculated server-side. Do not send amount in request.',
            ], 400);
        }

        try {
            // Load application
            $application = Application::findOrFail($validated['application_id']);

            // SECURITY: Verify ownership
            if ($application->user_id !== $request->user()->id) {
                Log::warning('Unauthorized payment initiation attempt', [
                    'user_id' => $request->user()->id,
                    'application_id' => $application->id,
                    'application_owner_id' => $application->user_id,
                ]);

                throw PaymentNotAllowedException::unauthorized($application->id);
            }

            // SECURITY: Validate payment eligibility
            $this->feeService->validatePaymentEligibility($application);

            // SECURITY: Calculate fee server-side (authoritative source)
            $feeBreakdown = $this->feeService->calculateFee($application);
            $amount = $feeBreakdown['total'];
            $currency = $validated['currency'] ?? 'GHS';

            $useOrchestrator = in_array($validated['gateway'], ['gcb', 'paystack', 'auto'], true);

            // Use database transaction for atomic payment record creation
            $result = DB::transaction(function () use ($application, $validated, $amount, $currency, $useOrchestrator) {
                $initialGateway = $validated['gateway'] === 'auto'
                    ? PaymentGateway::GCB
                    : ($validated['gateway'] === 'bank_transfer' ? PaymentGateway::GCB : PaymentGateway::from($validated['gateway']));

                if (!$useOrchestrator) {
                    $initialGateway = PaymentGateway::GCB; // bank_transfer: store as gcb for column; MultiPaymentService handles flow
                }

                // Create payment record FIRST with status='initiated'
                $payment = Payment::create([
                    'application_id' => $application->id,
                    'user_id' => $application->user_id,
                    'gateway' => $initialGateway,
                    'transaction_reference' => $this->generateTransactionReference($validated['gateway']),
                    'payment_provider' => $validated['gateway'],
                    'amount' => $amount, // Server-calculated amount
                    'currency' => $currency,
                    'status' => 'initiated',
                    'provider_response' => [
                        'initiated_at' => now()->toIso8601String(),
                    ],
                ]);

                Log::info('Payment initiated with server-calculated amount', [
                    'payment_id' => $payment->id,
                    'application_id' => $application->id,
                    'amount' => $amount,
                    'currency' => $currency,
                    'gateway' => $validated['gateway'],
                ]);

                if ($useOrchestrator) {
                    // GCB-first with Paystack fallback via PaymentGatewayService
                    $gatewayResult = $this->gatewayService->initiatePayment($payment, $validated['gateway']);
                    $payment->update([
                        'gateway_reference' => $gatewayResult['gateway_reference'] ?? $gatewayResult['reference'] ?? null,
                        'transaction_reference' => $gatewayResult['reference'] ?? $payment->transaction_reference,
                        'payment_provider' => $payment->gateway->value,
                        'provider_response' => array_merge(
                            $payment->provider_response ?? [],
                            $gatewayResult
                        ),
                    ]);
                } else {
                    // bank_transfer: legacy flow via MultiPaymentService
                    $gatewayResult = $this->paymentService->initializePayment(
                        $application,
                        $validated['gateway'],
                        $currency,
                        $validated['callback_url'] ?? null
                    );
                    if ($gatewayResult['success']) {
                        $payment->update([
                            'transaction_reference' => $gatewayResult['reference'] ?? $payment->transaction_reference,
                            'provider_response' => array_merge(
                                $payment->provider_response ?? [],
                                $gatewayResult
                            ),
                        ]);
                    }
                }

                return [
                    'payment' => $payment,
                    'gateway_result' => $gatewayResult,
                ];
            });

            if ($result['gateway_result']['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment initiated successfully',
                    'payment' => $result['payment'],
                    'gateway_data' => $result['gateway_result'],
                    'amount' => $amount,
                    'currency' => $currency,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['gateway_result']['message'] ?? 'Payment initialization failed',
                'payment' => $result['payment'],
            ], 400);

        } catch (PaymentNotAllowedException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Payment initiation error', [
                'user_id' => $request->user()->id,
                'application_id' => $validated['application_id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment initiation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate unique transaction reference.
     *
     * @param string $gateway
     * @return string
     */
    protected function generateTransactionReference(string $gateway): string
    {
        $prefix = strtoupper(substr($gateway, 0, 3));
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(substr(uniqid(), -6));

        return "{$prefix}-{$timestamp}-{$random}";
    }


    /**
     * Verify payment status using the MultiPaymentService.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'reference' => 'required|string',
        ]);

        $reference = $request->input('reference');

        try {
            $result = $this->paymentService->verifyPayment($reference);
            
            // If payment is completed, ensure we return success
            if (isset($result['payment']) && $result['payment']->status === 'paid') {
                return response()->json([
                    'success' => true,
                    'status' => 'paid',
                    'message' => 'Payment completed successfully',
                    'payment' => $result['payment'],
                ]);
            }
            
            // If payment is pending verification (bank transfer)
            if (isset($result['payment']) && $result['payment']->status === 'pending_verification') {
                return response()->json([
                    'success' => true,
                    'status' => 'pending_verification',
                    'message' => 'Payment received and is being verified',
                    'payment' => $result['payment'],
                ]);
            }
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Payment verification error', [
                'reference' => $reference,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Payment verification failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Demo payment simulation - creates a completed payment immediately.
     */
    public function simulatePayment(Request $request): JsonResponse
    {
        // CRITICAL: Only allow in non-production environments
        if (!app()->environment('local', 'testing', 'development')) {
            abort(404);
        }

        $request->validate([
            'application_id' => 'required|exists:applications,id',
        ]);

        $application = Application::findOrFail($request->input('application_id'));

        // Check if user owns this application
        if ($application->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if already paid
        $existingPayment = Payment::where('application_id', $application->id)
            ->where('status', 'completed')
            ->first();

        if ($existingPayment) {
            return response()->json([
                'success' => true,
                'message' => 'Application already paid',
                'payment' => $existingPayment,
            ]);
        }

        // Create demo payment (amount in pesewas/cents)
        $payment = Payment::create([
            'application_id' => $application->id,
            'user_id' => $application->user_id,
            'transaction_reference' => 'DEMO-' . strtoupper(uniqid()),
            'payment_provider' => 'demo',
            'amount' => $application->total_fee ?? 26000,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => now(),
            'provider_response' => [
                'demo_mode' => true,
                'message' => 'Demo payment - no actual transaction',
            ],
        ]);

        // Update application status
        if (in_array($application->status, ['submitted_awaiting_payment', 'pending_payment', 'draft'])) {
            $this->applicationService->confirmPayment($application);
            
            // Submit the application to trigger routing
            $this->applicationService->submit($application->fresh());
        }

        return response()->json([
            'success' => true,
            'message' => 'Demo payment completed successfully',
            'demo_note' => 'This is for demo purposes only - no actual payment was processed',
            'payment' => $payment,
            'application_status' => $application->fresh()->status,
        ]);
    }
}
