<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TrackingController extends Controller
{
    /**
     * Step 1: Initiate tracking — look up application by reference number,
     * generate OTP, "send" it via SMS to the applicant's phone, and
     * return a masked phone number so the frontend can show it.
     */
    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference_number' => 'required|string|min:5',
        ]);

        $application = Application::where('reference_number', $validated['reference_number'])->first();

        if (!$application) {
            return response()->json([
                'message' => 'No application found with this reference number.',
            ], 404);
        }

        $phone = $application->phone;

        if (!$phone) {
            // No phone on file — fall back to login prompt
            return response()->json([
                'verification_method' => 'login',
                'message' => 'No phone number on file. Please log in to view your application status.',
            ]);
        }

        // Generate a 6-digit OTP and cache it for 10 minutes
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $cacheKey = 'track_otp_' . md5($validated['reference_number']);

        Cache::put($cacheKey, [
            'otp' => $otp,
            'application_id' => $application->id,
            'attempts' => 0,
        ], now()->addMinutes(10));

        // In production this would call an SMS gateway.
        // For development we log it so testers can read the OTP.
        \Log::info("Tracking OTP for {$application->reference_number}: {$otp}");

        // Mask phone: show last 4 digits only
        $maskedPhone = str_repeat('*', max(0, strlen($phone) - 4)) . substr($phone, -4);

        return response()->json([
            'verification_method' => 'sms',
            'masked_phone' => $maskedPhone,
            'message' => 'A verification code has been sent to your registered phone number.',
            // In dev mode, include the OTP for easy testing
            'dev_otp' => app()->environment('local') ? $otp : null,
        ]);
    }

    /**
     * Step 2: Verify OTP and return the application status / applicant name.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reference_number' => 'required|string|min:5',
            'otp' => 'required|string|size:6',
        ]);

        $cacheKey = 'track_otp_' . md5($validated['reference_number']);
        $cached = Cache::get($cacheKey);

        if (!$cached) {
            return response()->json([
                'message' => 'Verification code has expired. Please request a new one.',
            ], 422);
        }

        // Rate-limit verification attempts
        if ($cached['attempts'] >= 5) {
            Cache::forget($cacheKey);
            return response()->json([
                'message' => 'Too many incorrect attempts. Please request a new verification code.',
            ], 429);
        }

        if ($cached['otp'] !== $validated['otp']) {
            $cached['attempts']++;
            Cache::put($cacheKey, $cached, now()->addMinutes(10));
            return response()->json([
                'message' => 'Invalid verification code. Please try again.',
            ], 422);
        }

        // OTP is valid — retrieve and return application details
        Cache::forget($cacheKey);

        $application = Application::with(['visaType', 'statusHistory' => function ($q) {
            $q->orderBy('created_at', 'asc');
        }])->find($cached['application_id']);

        if (!$application) {
            return response()->json(['message' => 'Application not found.'], 404);
        }

        return response()->json([
            'verified' => true,
            'applicant_name' => trim($application->first_name . ' ' . $application->last_name),
            'reference_number' => $application->reference_number,
            'status' => $application->status,
            'visa_type' => $application->visaType->name ?? null,
            'submitted_at' => $application->submitted_at?->toIso8601String(),
            'decided_at' => $application->decided_at?->toIso8601String(),
            'decision_notes' => $application->status === 'denied' ? $application->decision_notes : null,
            'timeline' => $application->statusHistory->map(fn($h) => [
                'status' => $h->to_status,
                'changed_at' => $h->created_at->toIso8601String(),
            ]),
        ]);
    }
}
