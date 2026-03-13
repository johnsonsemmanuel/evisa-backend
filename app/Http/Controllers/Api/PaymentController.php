<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Payment;
use App\Services\MultiPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        protected MultiPaymentService $paymentService
    ) {}

    /**
     * Get available payment methods.
     */
    public function methods(Request $request): JsonResponse
    {
        $countryCode = $request->query('country', 'GH');
        
        return response()->json([
            'methods' => $this->paymentService->getAvailablePaymentMethods($countryCode),
        ]);
    }

    /**
     * Initialize payment for an application.
     */
    public function initialize(Request $request, Application $application): JsonResponse
    {
        // Verify ownership
        if ($application->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Verify application exists and is in a payable state
        if (!$application->exists) {
            return response()->json(['message' => 'Application not found'], 404);
        }

        if (!in_array($application->status, ['draft', 'pending_payment', 'submitted_awaiting_payment'])) {
            return response()->json(['message' => 'Application is not in a payable state'], 422);
        }

        $validated = $request->validate([
            'payment_method' => 'required|string',
            'currency' => 'nullable|string|in:GHS,USD,EUR,GBP',
            'callback_url' => 'nullable|url',
        ]);

        $result = $this->paymentService->initializePayment(
            $application,
            $validated['payment_method'],
            $validated['currency'] ?? 'GHS',
            $validated['callback_url'] ?? null
        );

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'] ?? 'Payment initialization failed',
            ], 400);
        }

        return response()->json($result);
    }

    /**
     * Verify payment status.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference' => 'required|string',
        ]);

        $result = $this->paymentService->verifyPayment($validated['reference']);

        return response()->json($result);
    }

    /**
     * Get payment history for an application.
     */
    public function history(Request $request, Application $application): JsonResponse
    {
        // Verify ownership or admin
        if ($application->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $payments = Payment::where('application_id', $application->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'reference' => $p->transaction_reference,
                'provider' => $p->payment_provider,
                'amount' => $p->amount,
                'currency' => $p->currency,
                'status' => $p->status,
                'paid_at' => $p->paid_at?->toIso8601String(),
                'created_at' => $p->created_at->toIso8601String(),
            ]);

        return response()->json(['payments' => $payments]);
    }

    /**
     * Upload bank transfer proof.
     */
    public function uploadProof(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference' => 'required|string',
            'proof' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $payment = Payment::where('transaction_reference', $validated['reference'])
            ->where('payment_provider', 'bank_transfer')
            ->first();

        if (!$payment) {
            return response()->json(['message' => 'Bank transfer payment not found'], 404);
        }

        // Verify ownership
        if ($payment->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Store proof
        $path = $request->file('proof')->store('payment-proofs', 'private');

        $payment->update([
            'status' => 'pending_verification',
            'metadata' => array_merge($payment->metadata ?? [], [
                'proof_path' => $path,
                'proof_uploaded_at' => now()->toIso8601String(),
            ]),
        ]);

        return response()->json([
            'message' => 'Proof uploaded successfully. Verification in progress.',
            'status' => 'pending_verification',
        ]);
    }

    /**
     * Admin: Confirm bank transfer payment.
     */
    public function confirmBankTransfer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference' => 'required|string',
        ]);

        $payment = Payment::where('transaction_reference', $validated['reference'])
            ->where('payment_provider', 'bank_transfer')
            ->first();

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        $proofUrl = $payment->metadata['proof_path'] ?? null;

        $result = $this->paymentService->confirmBankTransfer(
            $validated['reference'],
            $proofUrl ?? '',
            $request->user()->id
        );

        if (!$result['success']) {
            return response()->json(['message' => $result['message'] ?? 'Confirmation failed'], 400);
        }

        return response()->json([
            'message' => 'Bank transfer confirmed',
            'payment' => $result['payment'],
        ]);
    }

    /**
     * Handle test payment callback (for development).
     */
    public function testPaymentCallback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference' => 'required|string',
            'status' => 'required|string|in:success,failed',
        ]);

        $payment = Payment::where('transaction_reference', $validated['reference'])->first();

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        if ($validated['status'] === 'success') {
            $payment->update([
                'status' => 'completed',
                'paid_at' => now(),
                'provider_reference' => 'TEST-' . now()->timestamp,
            ]);

            $this->paymentService->onPaymentSuccess($payment);

            return response()->json([
                'success' => true,
                'message' => 'Test payment completed successfully',
                'payment' => $payment->fresh(),
            ]);
        } else {
            $payment->update(['status' => 'failed']);

            return response()->json([
                'success' => false,
                'message' => 'Test payment failed',
                'payment' => $payment->fresh(),
            ]);
        }
    }
    public function statistics(Request $request): JsonResponse
    {
        $days = $request->query('days', 30);
        $startDate = now()->subDays($days);

        $stats = [
            'total_revenue' => Payment::where('status', 'completed')
                ->where('paid_at', '>=', $startDate)
                ->sum('amount'),
            'total_transactions' => Payment::where('status', 'completed')
                ->where('paid_at', '>=', $startDate)
                ->count(),
            'by_provider' => Payment::where('status', 'completed')
                ->where('paid_at', '>=', $startDate)
                ->selectRaw('payment_provider, COUNT(*) as count, SUM(amount) as total')
                ->groupBy('payment_provider')
                ->get(),
            'by_status' => Payment::where('created_at', '>=', $startDate)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
            'pending_bank_transfers' => Payment::where('payment_provider', 'bank_transfer')
                ->where('status', 'pending_verification')
                ->count(),
        ];

        return response()->json(['statistics' => $stats]);
    }
}
