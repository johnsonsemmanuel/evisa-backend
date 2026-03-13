<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AeropassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AeropassController extends Controller
{
    public function __construct(
        private AeropassService $aeropassService
    ) {}

    /**
     * Handle Interpol nominal verification callback from Aeropass
     * 
     * POST /api/aeropass/interpol-nominal-verification/callback
     */
    public function interpolCallback(Request $request): JsonResponse
    {
        // Validate authorization header
        if (!$this->validateAuthorization($request)) {
            return response()->json([
                'uniqueReferenceId' => $request->input('uniqueReferenceId', ''),
                'responseCode' => '401',
                'errorMessage' => 'Unauthorized request',
            ], 401);
        }

        // Validate request data
        $validator = Validator::make($request->all(), [
            'uniqueReferenceId' => 'required|string',
            'firstName' => 'required|string',
            'surname' => 'required|string',
            'dateOfBirth' => 'required|string|date_format:d/m/Y',
            'interpolNominalMatched' => 'required|string|in:Yes,No',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'uniqueReferenceId' => $request->input('uniqueReferenceId', ''),
                'responseCode' => '400',
                'errorMessage' => 'Missing mandatory field(s): ' . implode(', ', array_keys($validator->errors()->toArray())),
            ], 400);
        }

        // Process callback
        $result = $this->aeropassService->processInterpolCallback($request->all());

        $responseCode = $result['response_code'] ?? ($result['success'] ? '200' : '500');
        $httpStatus = (int) $responseCode;

        return response()->json([
            'uniqueReferenceId' => $request->input('uniqueReferenceId'),
            'responseCode' => $responseCode,
            'errorMessage' => $result['success'] ? null : ($result['error'] ?? 'Unknown error'),
        ], $httpStatus);
    }

    /**
     * Handle E-Visa check request from Aeropass
     * 
     * POST /api/aeropass/visa-check
     */
    public function visaCheck(Request $request): JsonResponse
    {
        // Validate authorization header
        if (!$this->validateAuthorization($request)) {
            return response()->json([
                'uniqueReferenceId' => $request->input('uniqueReferenceId', ''),
                'errorMessage' => 'Unauthorized request',
            ], 401);
        }

        // Validate request data
        $validator = Validator::make($request->all(), [
            'uniqueReferenceId' => 'required|string',
            'firstName' => 'required|string',
            'surname' => 'required|string',
            'dateOfBirth' => 'required|string|date_format:Y-m-d',
            'nationality' => 'required|string|size:3', // ISO 3166 Alpha-3
            'travelDocNumber' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'uniqueReferenceId' => $request->input('uniqueReferenceId', ''),
                'errorMessage' => 'Missing mandatory field(s): ' . implode(', ', array_keys($validator->errors()->toArray())),
            ], 400);
        }

        // Process E-Visa check
        $result = $this->aeropassService->processEVisaCheck($request->all());

        $httpStatus = $result['response_code'] ?? ($result['success'] ? 200 : 500);

        if ($result['success']) {
            return response()->json($result['data'], $httpStatus);
        } else {
            return response()->json([
                'uniqueReferenceId' => $request->input('uniqueReferenceId', ''),
                'errorMessage' => $result['error'] ?? 'Unknown error',
            ], $httpStatus);
        }
    }

    /**
     * Validate Basic Authorization header
     */
    private function validateAuthorization(Request $request): bool
    {
        $authHeader = $request->header('Authorization');
        
        if (!$authHeader || !str_starts_with($authHeader, 'Basic ')) {
            Log::warning('Aeropass request missing or invalid Authorization header', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            return false;
        }

        $credentials = base64_decode(substr($authHeader, 6));
        [$username, $password] = explode(':', $credentials, 2);

        $expectedUsername = config('services.aeropass.api_username');
        $expectedPassword = config('services.aeropass.api_password');

        if ($username !== $expectedUsername || $password !== $expectedPassword) {
            Log::warning('Aeropass request with invalid credentials', [
                'ip' => $request->ip(),
                'username' => $username,
                'user_agent' => $request->userAgent(),
            ]);
            return false;
        }

        return true;
    }
}