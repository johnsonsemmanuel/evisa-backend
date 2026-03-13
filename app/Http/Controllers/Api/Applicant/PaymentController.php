<?php

namespace App\Http\Controllers\Api\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Payment;
use App\Services\ApplicationService;
use App\Services\MultiPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected ApplicationService $applicationService;
    protected MultiPaymentService $paymentService;

    public function __construct(ApplicationService $applicationService, MultiPaymentService $paymentService)
    {
        $this->applicationService = $applicationService;
        $this->paymentService = $paymentService;
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
            
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Payment verification error', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed',
            ], 500);
        }
    }

    /**
     * Demo payment simulation - creates a completed payment immediately.
     */
    public function simulatePayment(Request $request): JsonResponse
    {
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

        // Create demo payment
        $payment = Payment::create([
            'application_id' => $application->id,
            'user_id' => $application->user_id,
            'transaction_reference' => 'DEMO-' . strtoupper(uniqid()),
            'payment_provider' => 'demo',
            'amount' => $application->total_fee ?? 260.00,
            'currency' => 'USD',
            'status' => 'completed',
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
