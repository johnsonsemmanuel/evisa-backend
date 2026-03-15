<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BorderVerificationService;
use App\Models\EtaApplication;
use App\Models\Application;
use App\Models\BoardingAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BorderController extends Controller
{
    protected BorderVerificationService $verificationService;

    public function __construct(BorderVerificationService $verificationService)
    {
        $this->verificationService = $verificationService;
    }

    /**
     * Verify travel authorization.
     */
    public function verifyTravel(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'passport_number' => 'required|string|max:50',
                'nationality' => 'required|string|size:2',
                'eta_number' => 'nullable|string|max:30',
                'visa_id' => 'nullable|string|max:50',
            ]);

            $result = $this->verificationService->verifyAuthorization(
                $validated['passport_number'],
                $validated['nationality'],
                $validated['eta_number'] ?? null,
                $validated['visa_id'] ?? null
            );

            // Return appropriate HTTP status based on result
            $httpStatus = match($result['status']) {
                'AUTHORIZED' => 200,
                'ETA_REQUIRED', 'VISA_REQUIRED', 'DENIED' => 403,
                default => 200,
            };

            return response()->json($result, $httpStatus);

        } catch (ValidationException $e) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid request data',
                    'details' => $e->errors(),
                ]
            ], 422);
        } catch (\Exception $e) {
            Log::error('Border verification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => [
                    'code' => 'SYSTEM_UNAVAILABLE',
                    'message' => 'Service temporarily unavailable',
                ]
            ], 503);
        }
    }

    /**
     * Generate Boarding Authorization Code.
     */
    public function generateBac(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'passport_number' => 'required|string|max:50',
                'nationality' => 'required|string|size:2',
                'eta_number' => 'nullable|string|max:30',
                'visa_id' => 'nullable|string|max:50',
            ]);

            // First verify the authorization
            $verification = $this->verificationService->verifyAuthorization(
                $validated['passport_number'],
                $validated['nationality'],
                $validated['eta_number'] ?? null,
                $validated['visa_id'] ?? null
            );

            if ($verification['status'] !== 'AUTHORIZED') {
                return response()->json([
                    'error' => [
                        'code' => 'NOT_AUTHORIZED',
                        'message' => 'Cannot generate BAC: traveler not authorized',
                        'details' => $verification,
                    ]
                ], 403);
            }

            // Generate BAC
            $bacData = [
                'passport_number' => $validated['passport_number'],
                'nationality' => $validated['nationality'],
                'type' => $verification['authorization_type'],
                'eta_number' => $verification['eta_number'] ?? null,
                'visa_id' => $verification['visa_id'] ?? null,
            ];

            $bac = $this->verificationService->generateBac($bacData, auth()->id());

            return response()->json([
                'boarding_authorization_code' => $bac->authorization_code,
                'valid_until' => $bac->expiry_timestamp->format('Y-m-d H:i:s'),
                'authorization_details' => $verification,
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid request data',
                    'details' => $e->errors(),
                ]
            ], 422);
        } catch (\Exception $e) {
            Log::error('BAC generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => [
                    'code' => 'SYSTEM_UNAVAILABLE',
                    'message' => 'Service temporarily unavailable',
                ]
            ], 503);
        }
    }

    /**
     * Confirm entry (mark authorization as used).
     */
    public function confirmEntry(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'eta_number' => 'nullable|string|max:30',
                'visa_id' => 'nullable|string|max:50',
                'passport_number' => 'required|string|max:50',
                'port_of_entry' => 'required|string|max:100',
            ]);

            if (!$validated['eta_number'] && !$validated['visa_id']) {
                return response()->json([
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Either eta_number or visa_id is required',
                    ]
                ], 422);
            }

            $passportNumber = strtoupper(trim($validated['passport_number']));

            // Handle ETA entry
            if ($validated['eta_number']) {
                $eta = EtaApplication::where('eta_number', $validated['eta_number'])->first();

                if (!$eta) {
                    return response()->json([
                        'error' => [
                            'code' => 'NOT_FOUND',
                            'message' => 'ETA not found',
                        ]
                    ], 404);
                }

                // Check if already used
                if ($eta->status === 'used') {
                    return response()->json([
                        'error' => [
                            'code' => 'ALREADY_USED',
                            'message' => 'This ETA has already been used for entry',
                            'entry_date' => $eta->entry_date?->format('Y-m-d H:i:s'),
                            'port_of_entry' => $eta->port_of_entry_actual,
                        ]
                    ], 403);
                }

                // Verify passport matches
                $storedPassport = Crypt::decryptString($eta->passport_number_encrypted);
                if (strtoupper($storedPassport) !== $passportNumber) {
                    return response()->json([
                        'error' => [
                            'code' => 'PASSPORT_MISMATCH',
                            'message' => 'Passport number does not match ETA record',
                        ]
                    ], 403);
                }

                // Mark as used
                $this->verificationService->markAsUsed(
                    $eta,
                    $validated['port_of_entry'],
                    auth()->id()
                );

                return response()->json([
                    'message' => 'Entry confirmed',
                    'eta_number' => $eta->eta_number,
                    'entry_date' => $eta->entry_date->format('Y-m-d H:i:s'),
                    'port_of_entry' => $eta->port_of_entry_actual,
                    'officer_id' => auth()->id(),
                ]);
            }

            // Handle Visa entry
            if ($validated['visa_id']) {
                $visa = Application::where('reference_number', $validated['visa_id'])->first();

                if (!$visa) {
                    return response()->json([
                        'error' => [
                            'code' => 'NOT_FOUND',
                            'message' => 'Visa not found',
                        ]
                    ], 404);
                }

                if ($visa->entry_type === 'single' && $visa->last_entry_date) {
                    return response()->json([
                        'error' => [
                            'code' => 'SINGLE_ENTRY_USED',
                            'message' => 'This single-entry visa has already been used',
                            'entry_date' => $visa->last_entry_date,
                        ]
                    ], 403);
                }

                $storedPassport = Crypt::decryptString($visa->passport_number_encrypted);
                if (strtoupper($storedPassport) !== $passportNumber) {
                    return response()->json([
                        'error' => [
                            'code' => 'PASSPORT_MISMATCH',
                            'message' => 'Passport number does not match visa record',
                        ]
                    ], 403);
                }

                $this->verificationService->markAsUsed(
                    $visa,
                    $validated['port_of_entry'],
                    auth()->id()
                );
                $stationId = auth()->user()->station_id ?? auth()->user()->border_station_id ?? null;
                broadcast(new \App\Events\BorderVerificationResult($visa, 'AUTHORIZED', $validated['port_of_entry'], $stationId));

                return response()->json([
                    'message' => 'Entry confirmed',
                    'visa_id' => $visa->reference_number,
                    'entry_date' => $visa->last_entry_date->format('Y-m-d H:i:s'),
                    'port_of_entry' => $visa->last_port_of_entry,
                    'officer_id' => auth()->id(),
                ]);
            }

        } catch (ValidationException $e) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid request data',
                    'details' => $e->errors(),
                ]
            ], 422);
        } catch (\Exception $e) {
            Log::error('Entry confirmation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => [
                    'code' => 'SYSTEM_UNAVAILABLE',
                    'message' => 'Service temporarily unavailable',
                ]
            ], 503);
        }
    }

    /**
     * Verify a Boarding Authorization Code.
     */
    public function verifyBac(string $code): JsonResponse
    {
        try {
            $result = $this->verificationService->verifyBac($code);

            if (!$result) {
                return response()->json([
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => 'BAC not found or expired',
                    ]
                ], 404);
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('BAC verification failed', [
                'code' => $code,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => [
                    'code' => 'SYSTEM_UNAVAILABLE',
                    'message' => 'Service temporarily unavailable',
                ]
            ], 503);
        }
    }
}
