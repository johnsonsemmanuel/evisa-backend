<?php

namespace App\Http\Controllers\Api\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Payment;
use App\Services\ApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected ApplicationService $applicationService;

    public function __construct(ApplicationService $applicationService)
    {
        $this->applicationService = $applicationService;
    }

    /**
     * Demo payment verification - automatically approves payment for demo purposes.
     * In production, this would verify with actual payment gateway.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'reference' => 'required|string',
        ]);

        $reference = $request->input('reference');

        // Find payment by transaction reference
        $payment = Payment::where('transaction_reference', $reference)->first();

        if (!$payment) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found',
            ], 404);
        }

        // DEMO MODE: Auto-approve payment
        if ($payment->status === 'pending') {
            $payment->update([
                'status' => 'completed',
                'paid_at' => now(),
                'provider_response' => [
                    'demo_mode' => true,
                    'message' => 'Payment auto-approved for demo purposes',
                    'verified_at' => now()->toIso8601String(),
                ],
            ]);

            // Update application status
            $application = $payment->application;
            
            if ($application && in_array($application->status, ['submitted_awaiting_payment', 'pending_payment', 'draft'])) {
                $this->applicationService->confirmPayment($application);
                
                // Submit the application to trigger routing
                $this->applicationService->submit($application->fresh());
                
                Log::info('Demo payment completed and application submitted', [
                    'payment_id' => $payment->id,
                    'application_id' => $application->id,
                    'reference' => $reference,
                    'new_status' => $application->fresh()->status,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'status' => $payment->status,
            'message' => 'Payment verified successfully (Demo Mode)',
            'demo_note' => 'This is a demo payment - no actual transaction was processed',
            'application_status' => $payment->application->status ?? null,
        ]);
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
