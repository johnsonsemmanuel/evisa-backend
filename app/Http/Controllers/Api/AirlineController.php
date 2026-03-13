<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\EtaApplication;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class AirlineController extends Controller
{
    public function __construct(
        protected QrCodeService $qrCodeService
    ) {}

    /**
     * Verify passenger travel authorization (for airlines).
     * Airlines use this before boarding to verify visa/ETA status.
     */
    public function verifyPassenger(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'passport_number' => 'required|string',
            'passport_nationality' => 'required|string|size:2',
            'date_of_birth' => 'required|date',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'flight_number' => 'required|string',
            'flight_date' => 'required|date',
            'destination' => 'required|string|in:GH,GHA,GHANA',
        ]);

        Log::info('Airline API verification request', [
            'passport' => substr($validated['passport_number'], 0, 3) . '****',
            'flight' => $validated['flight_number'],
        ]);

        // Search for matching eVisa
        $evisaResult = $this->findMatchingEvisa($validated);
        if ($evisaResult['found']) {
            return response()->json($this->formatAirlineResponse($evisaResult, 'evisa'));
        }

        // Search for matching ETA
        $etaResult = $this->findMatchingEta($validated);
        if ($etaResult['found']) {
            return response()->json($this->formatAirlineResponse($etaResult, 'eta'));
        }

        // Use ETA Eligibility Service for comprehensive check
        $eligibilityService = app(\App\Services\EtaEligibilityService::class);
        $authType = $eligibilityService->getAuthorizationType(strtoupper($validated['passport_nationality']));

        // Check if nationality is visa-exempt (ECOWAS) but needs ETA
        if ($authType['authorization'] === 'eta') {
            return response()->json([
                'authorized' => false,
                'authorization_type' => 'eta_required',
                'message' => $authType['message'] . ' - ETA not found for this passenger',
                'eta_type' => $authType['eta_type'] ?? null,
                'recommendations' => [
                    'Passenger requires ETA registration',
                    'Apply at ghanaeta.gov.gh',
                ],
            ], 404);
        }

        // Ghana citizens - no authorization needed
        if ($authType['type'] === 'citizen') {
            return response()->json([
                'authorized' => true,
                'authorization_type' => 'citizen',
                'message' => 'Ghana citizen - no visa required',
                'nationality' => $validated['passport_nationality'],
                'notes' => 'Passenger is from ECOWAS member state. Valid passport required for entry.',
            ]);
        }

        return response()->json([
            'authorized' => false,
            'authorization_type' => 'none',
            'message' => 'No valid travel authorization found',
            'recommendations' => [
                'Verify passport details are correct',
                'Check if passenger has applied for eVisa or ETA',
                'Passenger may need to apply at ghanaevisa.gov.gh',
            ],
        ], 404);
    }

    /**
     * Verify QR code from eVisa/ETA document.
     */
    public function verifyQrCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_data' => 'required|string',
            'passport_number' => 'nullable|string',
        ]);

        $result = $this->qrCodeService->verifyQrCode($validated['qr_data']);

        if (!$result['valid']) {
            return response()->json([
                'authorized' => false,
                'error' => $result['error'] ?? 'QR verification failed',
            ], 400);
        }

        return response()->json([
            'authorized' => true,
            'authorization_type' => $result['type'],
            'document' => $result,
        ]);
    }

    /**
     * Batch verify multiple passengers.
     */
    public function batchVerify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'passengers' => 'required|array|min:1|max:100',
            'passengers.*.passport_number' => 'required|string',
            'passengers.*.passport_nationality' => 'required|string|size:2',
            'passengers.*.date_of_birth' => 'required|date',
            'passengers.*.first_name' => 'required|string',
            'passengers.*.last_name' => 'required|string',
            'flight_number' => 'required|string',
            'flight_date' => 'required|date',
        ]);

        $results = [];

        foreach ($validated['passengers'] as $index => $passenger) {
            $passenger['flight_number'] = $validated['flight_number'];
            $passenger['flight_date'] = $validated['flight_date'];
            $passenger['destination'] = 'GH';

            // Check eVisa
            $evisaResult = $this->findMatchingEvisa($passenger);
            if ($evisaResult['found']) {
                $results[] = array_merge(
                    ['passenger_index' => $index],
                    $this->formatAirlineResponse($evisaResult, 'evisa')
                );
                continue;
            }

            // Check ETA
            $etaResult = $this->findMatchingEta($passenger);
            if ($etaResult['found']) {
                $results[] = array_merge(
                    ['passenger_index' => $index],
                    $this->formatAirlineResponse($etaResult, 'eta')
                );
                continue;
            }

            // Check visa-exempt
            $ecowasCountries = ['NG', 'GH', 'SN', 'CI', 'ML', 'BF', 'NE', 'TG', 'BJ', 'SL', 'LR', 'GW', 'GM', 'CV'];
            if (in_array(strtoupper($passenger['passport_nationality']), $ecowasCountries)) {
                $results[] = [
                    'passenger_index' => $index,
                    'authorized' => true,
                    'authorization_type' => 'visa_exempt',
                    'message' => 'ECOWAS national',
                ];
                continue;
            }

            $results[] = [
                'passenger_index' => $index,
                'authorized' => false,
                'authorization_type' => 'none',
                'message' => 'No valid authorization found',
            ];
        }

        $authorized = collect($results)->where('authorized', true)->count();
        $unauthorized = count($results) - $authorized;

        return response()->json([
            'flight_number' => $validated['flight_number'],
            'flight_date' => $validated['flight_date'],
            'total_passengers' => count($results),
            'authorized_count' => $authorized,
            'unauthorized_count' => $unauthorized,
            'results' => $results,
        ]);
    }

    /**
     * Find matching eVisa for passenger.
     */
    protected function findMatchingEvisa(array $passenger): array
    {
        $applications = Application::where('status', 'approved')
            ->whereNotNull('decided_at')
            ->get();

        foreach ($applications as $app) {
            // Match passport number
            if (strtoupper($app->passport_number) !== strtoupper($passenger['passport_number'])) {
                continue;
            }

            // Check if visa is still valid
            $expiryDate = $app->decided_at->addDays($app->visaType?->max_duration_days ?? 90);
            if ($expiryDate < now()) {
                return [
                    'found' => true,
                    'valid' => false,
                    'expired' => true,
                    'application' => $app,
                    'expiry_date' => $expiryDate,
                ];
            }

            return [
                'found' => true,
                'valid' => true,
                'application' => $app,
                'expiry_date' => $expiryDate,
            ];
        }

        return ['found' => false];
    }

    /**
     * Find matching ETA for passenger.
     */
    protected function findMatchingEta(array $passenger): array
    {
        $etas = EtaApplication::where('status', 'approved')
            ->whereNotNull('approved_at')
            ->get();

        foreach ($etas as $eta) {
            $storedPassport = Crypt::decryptString($eta->passport_number_encrypted);
            if (strtoupper($storedPassport) !== strtoupper($passenger['passport_number'])) {
                continue;
            }

            // Check if ETA is still valid
            if ($eta->expires_at && $eta->expires_at < now()) {
                return [
                    'found' => true,
                    'valid' => false,
                    'expired' => true,
                    'eta' => $eta,
                    'expiry_date' => $eta->expires_at,
                ];
            }

            return [
                'found' => true,
                'valid' => true,
                'eta' => $eta,
                'expiry_date' => $eta->expires_at,
            ];
        }

        return ['found' => false];
    }

    /**
     * Format response for airline API.
     */
    protected function formatAirlineResponse(array $result, string $type): array
    {
        if (!$result['valid']) {
            return [
                'authorized' => false,
                'authorization_type' => $type,
                'message' => 'Authorization expired',
                'expired_on' => $result['expiry_date']->format('Y-m-d'),
            ];
        }

        if ($type === 'evisa') {
            $app = $result['application'];
            return [
                'authorized' => true,
                'authorization_type' => 'evisa',
                'reference_number' => $app->reference_number,
                'holder_name' => $app->first_name . ' ' . $app->last_name,
                'visa_type' => $app->visaType?->name,
                'entry_type' => $app->visaType?->entry_type ?? 'single',
                'valid_until' => $result['expiry_date']->format('Y-m-d'),
                'issued_on' => $app->decided_at->format('Y-m-d'),
            ];
        }

        $eta = $result['eta'];
        return [
            'authorized' => true,
            'authorization_type' => 'eta',
            'eta_number' => $eta->eta_number,
            'reference_number' => $eta->reference_number,
            'holder_name' => Crypt::decryptString($eta->first_name_encrypted) . ' ' . Crypt::decryptString($eta->last_name_encrypted),
            'entry_type' => $eta->entry_type,
            'valid_until' => $result['expiry_date']?->format('Y-m-d'),
            'issued_on' => $eta->approved_at?->format('Y-m-d'),
        ];
    }
}
